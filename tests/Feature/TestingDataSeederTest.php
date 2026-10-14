<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\ExtensionRequest;
use App\Models\FinancialReport;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\PehurProjectSeeder;
use Database\Seeders\TestingDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TestingDataSeederTest extends TestCase
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

    public function test_test_data_is_repeat_safe_and_preserves_real_data_and_tester_edits(): void
    {
        $this->seed(PehurProjectSeeder::class);
        $real = Project::where('reference_code', 'PHLCE-001')->sole()->getAttributes();
        $this->seed(TestingDataSeeder::class);
        $project = Project::where('reference_code', 'TEST-IDS-005')->sole();
        $project->update(['name' => 'Tester edited project']);
        $tables = ['projects', 'components', 'subcomponents', 'project_pipeline_steps', 'issues', 'comments', 'meetings', 'financial_reports', 'annual_financials', 'quarterly_financials', 'extension_requests'];
        $counts = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);
        $this->seed(TestingDataSeeder::class);
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame($real, Project::where('reference_code', 'PHLCE-001')->sole()->getAttributes());
        $this->assertSame('Tester edited project', $project->fresh()->name);
        $this->assertSame(10, Project::where('reference_code', 'like', 'TEST-IDS-%')->count());
        $this->assertSame(4, Project::where('reference_code', 'like', 'TEST-IDS-%')->where('phase', 'Pipeline')->count());
        $this->assertSame(3, Project::where('reference_code', 'like', 'TEST-IDS-%')->where('is_flagship', true)->count());
        $this->assertCount(4, $project->issues);
        $this->assertCount(6, $project->components);
        $this->assertCount(2, $project->components[1]->subcomponents);
        $this->assertCount(0, $project->components[0]->subcomponents);
    }

    public function test_seeded_pages_and_financial_year_filters_render_for_admin(): void
    {
        $this->seed(TestingDataSeeder::class);
        $this->actingAs(User::where('email', 'admin@ids.local')->sole());
        foreach (['/dashboard', '/dashboard/updates', '/projects', '/extensions', '/logs'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (config('portfolio_records') as $module => $definition) {
            $this->get(route($module.'.index'))->assertOk();
            if (auth()->user()->hasPortfolioPermission($module.'.create') && ! ($definition['existing_only'] ?? false)) {
                $this->get(route($module.'.create'))->assertOk();
            }
        }
        foreach (Project::all() as $project) {
            $this->get(route('projects.show', $project))->assertOk()->assertSee($project->name);
            $this->get(route('projects.edit', $project))->assertOk();
            $this->get(route('projects.show', ['project' => $project, 'tab' => $project->phase === 'Pipeline' ? 'pipeline' : 'physical']))->assertOk();
            foreach ($project->financialReports as $report) {
                $this->get(route('projects.show', ['project' => $project, 'tab' => 'financial', 'year' => $report->financial_year_id, 'quarter' => 1]))->assertOk()->assertSee($report->financialYear->label);
                $this->assertSame(20000000.0, (float) $report->quarters->sum('planned_disbursement'));
                $this->assertSame((float) $report->annuals->first()->disbursement_ytd, (float) $report->quarters->sum('actual_disbursement'));
            }
        }
    }

    public function test_seeded_director_isolation_and_extension_approval_workflow(): void
    {
        $this->seed(TestingDataSeeder::class);
        $director = User::where('email', 'director1@example.test')->sole();
        $own = Project::where('reference_code', 'TEST-IDS-005')->sole();
        $foreign = Project::where('reference_code', 'TEST-IDS-006')->sole();
        $this->actingAs($director)->get('/projects')->assertOk()->assertSee($own->name)->assertDontSee($foreign->name);
        $this->get(route('projects.show', $foreign))->assertForbidden();
        $this->get('/issues')->assertOk()->assertDontSee('TEST TEST-IDS-006 issue');
        $this->post(route('projects.comments.store', $foreign), ['body' => 'Unauthorized comment'])->assertForbidden();
        $this->assertDatabaseMissing('comments', ['body' => 'Unauthorized comment']);
        $extension = ExtensionRequest::whereHas('component', fn ($query) => $query->where('project_id', $own->id))->sole();
        $original = $extension->component->baseline_end->toDateString();
        $this->post(route('extensions.decide', $extension), ['decision' => 'Approved', 'remarks' => 'Self approval'])->assertForbidden();
        $reviewer = User::where('email', 'admin@ids.local')->sole();
        $this->actingAs($reviewer)->post(route('extensions.decide', $extension), ['decision' => 'Approved', 'remarks' => 'QA approved delivery adjustment.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Approved', $extension->fresh()->status);
        $this->assertSame($original, $extension->component->fresh()->baseline_end->toDateString());
        $this->assertSame($extension->requested_end_date->toDateString(), $extension->component->fresh()->effective_end->toDateString());
        $this->assertDatabaseHas('extension_decisions', ['request_id' => $extension->id, 'decision' => 'Approved']);
        $report = FinancialReport::where('project_id', $own->id)->firstOrFail();
        $this->get(route('financial-information.edit', $report))->assertOk();
        $parent = Comment::where('project_id', $own->id)->whereNull('parent_id')->sole();
        $this->actingAs($director)->post(route('projects.comments.store', $own), ['body' => 'QA response', 'parent_id' => $parent->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('comments', ['project_id' => $own->id, 'parent_id' => $parent->id, 'body' => 'QA response', 'author_id' => $director->id]);
    }

    public function test_seeded_record_detail_and_edit_pages_render_for_super_admin(): void
    {
        $this->seed(TestingDataSeeder::class);
        $this->actingAs(User::where('email', 'superadmin@ids.local')->sole());
        foreach (config('portfolio_records') as $module => $definition) {
            foreach ($definition['model']::query()->limit(2)->get() as $record) {
                $this->get(route($module.'.show', $record->id))->assertOk();
                if (! ($definition['append_only'] ?? false)) {
                    $this->get(route($module.'.edit', $record->id))->assertStatus($module === 'roles' && $record->id === auth()->user()->role_id ? 403 : 200);
                }
            }
        }
    }

    public function test_user_without_permissions_cannot_access_or_mutate_management_modules(): void
    {
        $this->seed(TestingDataSeeder::class);
        $this->actingAs(User::factory()->create(['role_id' => null, 'account_status' => 'Active']));
        foreach (config('portfolio_records') as $module => $definition) {
            $this->get(route($module.'.index'))->assertForbidden();
            $this->get(route($module.'.create'))->assertForbidden();
            $this->post(route($module.'.store'), [])->assertForbidden();
            $this->put(route($module.'.update', 1), [])->assertForbidden();
            $this->delete(route($module.'.destroy', 1))->assertForbidden();
        }
        $this->post('/extensions', [])->assertForbidden();
        $this->get('/logs')->assertForbidden();
        $this->assertDatabaseCount('projects', 10);
    }

    public function test_dummy_seeder_refuses_production_without_inserting_records(): void
    {
        $this->assertDatabaseCount('projects', 0);
        $this->app->instance('env', 'production');
        try {
            $this->app->make(TestingDataSeeder::class)->run();
            $this->fail('Production must reject dummy data.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('TestingDataSeeder cannot run in production.', $exception->getMessage());
        }
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('users', 0);
    }
}
