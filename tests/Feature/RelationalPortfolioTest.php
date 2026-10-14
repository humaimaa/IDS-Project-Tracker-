<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Component;
use App\Models\FinancialReport;
use App\Models\FinancialYear;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\PortfolioAccess;
use Database\Seeders\PehurProjectSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RelationalPortfolioTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portfolio.enabled' => true]);
        Route::setRoutes(new RouteCollection);
        Route::middleware('web')->group(base_path('routes/web.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    private function administrator(): User
    {
        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        foreach (PortfolioAccess::permissions() as $code) {
            $permission = Permission::firstOrCreate(['code' => $code]);
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permission->id]);
        }

        return User::factory()->create(['role_id' => $role->id, 'account_status' => 'Active']);
    }

    public function test_pehur_seeder_is_repeatable_and_preserves_unknowns_and_one_editable_financial_report(): void
    {
        $this->seed(PehurProjectSeeder::class);
        $this->seed(PehurProjectSeeder::class);

        $project = Project::sole();
        $this->assertSame('Swabi', $project->district->name);
        $this->assertSame(['Nowshera', 'Swabi'], $project->districts->pluck('name')->all());
        $this->assertSame('85.80', $project->reported_progress);
        $this->assertDatabaseCount('components', 4);
        $this->assertDatabaseCount('subcomponents', 0);
        $this->assertDatabaseCount('issues', 6);
        $this->assertDatabaseCount('financial_reports', 1);
        $this->assertDatabaseHas('components', ['name' => 'LOT-1', 'reported_progress' => null, 'baseline_end' => null]);
        $this->assertDatabaseHas('project_cost_versions', ['total_cost' => '15654000000']);
        $this->assertDatabaseHas('annual_financials', ['disbursement_target' => '6900000', 'disbursement_ytd' => '720000']);
        $this->assertDatabaseCount('annual_financials', 1);
        $this->assertArrayNotHasKey('status', FinancialReport::firstOrFail()->getAttributes());
    }

    public function test_database_pages_display_source_records_and_no_demo_projects(): void
    {
        $this->seed(PehurProjectSeeder::class);
        $project = Project::sole();
        $this->actingAs($this->administrator())->get('/dashboard')->assertOk()->assertSee('Live project portfolio');
        $this->get('/projects?district=Swabi')->assertOk()->assertSee('Pehur High Level Canal Extension Project');
        $this->get('/projects?district=Nowshera')->assertOk()->assertSee('Pehur High Level Canal Extension Project');
        foreach (['general', 'physical', 'financial'] as $tab) {
            $this->get('/projects/'.$project->id.'?tab='.$tab)->assertOk()->assertSee('Pehur High Level Canal Extension Project')->assertDontSee('Demo chat');
        }
        $this->get('/projects/'.$project->id.'?tab=financial')->assertSee('6.90')->assertSee('0.72')->assertDontSee('Approve report')->assertSee('Not supplied');
        $this->get('/issues')->assertOk()->assertSee('PC-1 revision');
    }

    public function test_pipeline_creation_copies_templates_without_physical_components_or_finances(): void
    {
        $this->seed(PehurProjectSeeder::class);
        $existing = Project::sole();
        $user = $this->administrator();

        $this->actingAs($user)->post('/projects', ['reference_code' => 'PIPELINE-TEST', 'name' => 'New pipeline project', 'phase' => 'Pipeline', 'lifecycle' => 'Active', 'sector_id' => $existing->sector_id, 'district_id' => $existing->district_id])->assertRedirect();

        $project = Project::where('reference_code', 'PIPELINE-TEST')->sole();
        $this->assertSame(5, $project->pipelineSteps()->count());
        $this->assertSame(0, $project->components()->count());
        $this->get('/projects/'.$project->id.'?tab=financial')->assertOk()->assertSee('Pipeline steps')->assertDontSee('Yearly and quarterly reports');
        $this->post('/activities', ['project_id' => $project->id, 'name' => 'Invalid', 'sequence_no' => 1, 'lifecycle' => 'Ongoing'])->assertSessionHasErrors(['record' => 'Pipeline projects use predefined steps and cannot have components.']);
        $this->assertSame(0, $project->components()->count());
    }

    public function test_writes_require_authenticated_authorized_users(): void
    {
        $project = Project::factory()->create();
        $this->post('/projects/'.$project->id.'/comments', ['body' => 'Guest'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post('/issues', ['project_id' => $project->id, 'title' => 'Forbidden', 'status' => 'Open'])->assertForbidden();
        $this->assertDatabaseCount('issues', 0);
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_comments_persist_and_cannot_reply_to_another_projects_comment(): void
    {
        $user = $this->administrator();
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $foreign = Comment::create(['project_id' => $other->id, 'author_id' => $user->id, 'body' => 'Another project']);
        $this->actingAs($user)->post('/projects/'.$project->id.'/comments', ['body' => 'Wrong parent', 'parent_id' => $foreign->id])->assertSessionHasErrors('parent_id');
        $this->post('/projects/'.$project->id.'/comments', ['body' => '<script>alert(1)</script>'])->assertRedirect();
        $this->get('/projects/'.$project->id)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertDatabaseHas('comments', ['project_id' => $project->id, 'body' => '<script>alert(1)</script>']);
    }

    public function test_progress_updates_reject_mixed_targets_and_do_not_overwrite_newer_progress(): void
    {
        $user = $this->administrator();
        $component = Component::factory()->create();
        $this->travelTo('2026-10-08');
        $this->actingAs($user)->post('/progress-updates', ['component_id' => $component->id, 'as_of_date' => '2026-10-08', 'progress_percent' => 60, 'status' => 'Ongoing'])->assertRedirect();
        $this->post('/progress-updates', ['component_id' => $component->id, 'as_of_date' => '2026-09-01', 'progress_percent' => 20, 'status' => 'Ongoing'])->assertRedirect();
        $this->assertSame('60.00', $component->fresh()->reported_progress);
        $this->post('/progress-updates', ['component_id' => $component->id, 'as_of_date' => '2026-10-08', 'progress_percent' => 50, 'status' => 'Completed'])->assertSessionHasErrors('progress_percent');
    }

    public function test_removed_approval_status_cannot_be_set_through_report_update(): void
    {
        $this->seed(PehurProjectSeeder::class);
        $report = FinancialReport::sole();
        $user = $this->administrator();
        $data = ['project_id' => $report->project_id, 'financial_year_id' => $report->financial_year_id, 'as_of_date' => '2026-09-09', 'revision_no' => 1, 'status' => 'Approved'];

        $this->actingAs($user)->put('/financial-reports/'.$report->id, $data)->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('status', $report->fresh()->getAttributes());
        $this->post('/financial-reports/'.$report->id.'/approve')->assertNotFound();
    }

    public function test_passwords_are_redacted_in_account_creation_logs(): void
    {
        $user = $this->administrator();
        $this->actingAs($user)->post('/users', ['name' => 'New officer', 'email' => 'officer@example.test', 'password' => 'LongExamplePassword123', 'account_status' => 'Active'])->assertRedirect();
        $created = User::where('email', 'officer@example.test')->sole();
        $this->assertDatabaseHas('audit_logs', ['module' => 'users', 'subject_id' => $created->id, 'action' => 'created']);
        $this->get('/logs')->assertSee('[redacted]')->assertDontSee('LongExamplePassword123');
    }

    public function test_empty_database_has_no_demo_fallback(): void
    {
        $response = $this->actingAs($this->administrator())->get('/dashboard')->assertOk();
        $this->assertSame([0, 0, 0, 0, 0], array_column($response->viewData('cards'), 'count'));
        $this->get('/projects')->assertSee('No projects match this selection.');
    }

    public function test_authorized_forms_render_and_preserve_existing_date_values(): void
    {
        $this->seed(PehurProjectSeeder::class);
        $this->actingAs($this->administrator());
        foreach (config('portfolio_records') as $module => $definition) {
            $this->get('/'.$module)->assertOk();
            if (! ($definition['existing_only'] ?? false)) {
                $this->get('/'.$module.'/create')->assertOk()->assertSee('Save');
            }
            $record = $definition['model']::first();
            if ($record && ! ($definition['append_only'] ?? false)) {
                $this->get('/'.$module.'/'.$record->id.'/edit')->assertStatus($module === 'roles' && $record->id === auth()->user()->role_id ? 403 : 200);
            }
        }
        $project = Project::sole();
        $this->get('/projects/create')->assertOk();
        $this->get('/projects/'.$project->id.'/edit')->assertOk()->assertSee('value="2027-06-30"', false)->assertSee('Extension approvers');
    }

    public function test_first_account_receives_administrator_access_without_seeded_credentials(): void
    {
        $this->seed(PehurProjectSeeder::class);
        $this->assertDatabaseCount('users', 0);

        $this->post('/account/setup', ['name' => 'Tracker owner', 'email' => 'owner@example.test', 'password' => 'StrongInitialPassword123', 'password_confirmation' => 'StrongInitialPassword123'])->assertRedirect(route('account.edit'));

        $user = User::sole();
        $this->assertSame('Super Admin', $user->role->name);
        $this->assertTrue($user->hasPortfolioPermission('projects.create'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_financial_figures_cannot_be_moved_to_another_report(): void
    {
        $this->seed(PehurProjectSeeder::class);
        $approved = FinancialReport::where('revision_no', 1)->sole();
        $year = FinancialYear::create(['label' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']);
        $draft = FinancialReport::create(['project_id' => $approved->project_id, 'financial_year_id' => $year->id, 'currency_code' => 'USD', 'as_of_date' => today(), 'revision_no' => 1, 'status' => 'Draft']);
        $annual = $approved->annuals()->sole();

        $this->actingAs($this->administrator())->put('/annual-financials/'.$annual->id, ['report_id' => $draft->id, 'currency_code' => 'USD', 'disbursement_target' => 100])->assertSessionHasErrors('report_id');

        $this->assertSame($approved->id, $annual->fresh()->report_id);
        $this->assertSame('6900000.00', $annual->fresh()->disbursement_target);
    }

    public function test_opened_comments_are_marked_read_for_only_the_current_user(): void
    {
        $project = Project::factory()->create();
        $author = User::factory()->create();
        $reader = $this->administrator();
        $comment = Comment::create(['project_id' => $project->id, 'author_id' => $author->id, 'body' => 'Project update']);
        $this->actingAs($reader)->get('/projects/'.$project->id)->assertViewHas('unreadCommentCount', 1);

        $this->post('/dashboard/comments/read', ['ids' => [$comment->id]])->assertOk()->assertJson(['saved' => true]);

        $this->assertDatabaseHas('comment_reads', ['user_id' => $reader->id, 'comment_id' => $comment->id, 'version' => 1]);
        $this->get('/projects/'.$project->id)->assertViewHas('unreadCommentCount', 0);
        $this->actingAs($this->administrator())->get('/projects/'.$project->id)->assertViewHas('unreadCommentCount', 1);
    }
}
