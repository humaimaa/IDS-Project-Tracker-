<?php

namespace Tests\Feature;

use App\Models\FinancialReport;
use App\Models\Permission;
use App\Models\PipelineStepTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PehurProjectSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineTemplateSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PortfolioNavigationPermissionsTest extends TestCase
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

    private function signIn(array $permissions): User
    {
        $this->seed(PermissionSeeder::class);
        $role = Role::create(['name' => 'Limited reviewer']);
        foreach ($permissions as $permission) {
            DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission_id' => Permission::where('code', $permission)->sole()->id]);
        }
        $user = User::factory()->create(['role_id' => $role->id, 'account_status' => 'Active']);
        $this->actingAs($user);

        return $user;
    }

    public function test_components_are_only_inside_data_management_and_dashboard_fragments_respect_permissions(): void
    {
        $this->signIn(['dashboard.view', 'activities.view', 'pipeline-templates.view']);
        $this->seed(PehurProjectSeeder::class);
        $this->get('/dashboard')->assertOk()->assertSee('Data Management')->assertSee('data-group="data-components"', false)->assertDontSee('data-group="activities"', false)->assertDontSee('data-dashboard-comments', false)->assertDontSee('data-dashboard-upcoming', false)->assertDontSee('id="global-search"', false);
        $this->getJson('/dashboard/updates')->assertOk()->assertExactJson(['comments' => '', 'meetings' => '', 'upcoming' => '', 'issues' => 0, 'issueProjects' => '']);
        $this->get('/activities')->assertOk();
        $this->get('/activities/create')->assertForbidden();
    }

    public function test_report_view_does_not_grant_access_to_financial_figures_or_approval(): void
    {
        $this->signIn(['projects.view', 'financial-reports.view']);
        $this->seed(PehurProjectSeeder::class);
        $report = FinancialReport::firstOrFail();
        $this->get(route('projects.show', ['project' => $report->project_id, 'tab' => 'financial']))->assertOk()->assertDontSee('Financial records and approval')->assertDontSee('Approve report')->assertDontSee('Annual figures')->assertDontSee('Total project cost')->assertDontSee('Donor financing');
        $this->get(route('financial-information.edit', $report))->assertForbidden();
        $this->assertArrayNotHasKey('status', $report->fresh()->getAttributes());
    }

    public function test_approval_endpoint_and_permission_are_removed_and_reports_remain_editable(): void
    {
        $this->signIn(['financial-reports.update', 'financial-reports.view']);
        $this->seed(PehurProjectSeeder::class);
        $report = FinancialReport::firstOrFail();
        $this->post('/financial-reports/'.$report->id.'/approve', ['approved_by' => 999])->assertNotFound();
        $this->assertDatabaseMissing('permissions', ['code' => 'financial-reports.approve']);
        $this->get(route('financial-information.edit', $report))->assertOk();
        $this->put(route('financial-reports.update', $report), ['project_id' => $report->project_id, 'financial_year_id' => $report->financial_year_id, 'as_of_date' => '2026-10-13', 'revision_no' => 1, 'source_notes' => 'Updated financial record', 'status' => 'Approved', 'approved_by' => 999])->assertSessionHasNoErrors();
        $this->assertSame('Updated financial record', $report->fresh()->source_notes);
        foreach (['status', 'approved_by', 'approved_at', 'approval_notes'] as $field) {
            $this->assertArrayNotHasKey($field, $report->fresh()->getAttributes());
        }
    }

    public function test_create_only_user_can_save_without_being_redirected_to_a_forbidden_list(): void
    {
        $this->signIn(['sectors.create']);
        $this->get(route('sectors.create'))->assertOk();
        $this->post(route('sectors.store'), ['name' => 'Permission test sector', 'code' => 'PERM', 'active' => 1])
            ->assertSessionHasNoErrors()->assertRedirect(route('account.edit'));
        $this->assertDatabaseHas('sectors', ['name' => 'Permission test sector']);
        $this->get(route('sectors.index'))->assertForbidden();
    }

    public function test_financial_form_does_not_offer_editing_existing_figures_with_create_permission(): void
    {
        $this->signIn(['financial-reports.update', 'annual-financials.create', 'quarterly-financials.create']);
        $this->seed(PehurProjectSeeder::class);
        $report = FinancialReport::whereHas('annuals')->firstOrFail();
        $this->get(route('financial-information.edit', $report))->assertOk()
            ->assertDontSee('name="years[0][annual][total_allocation]"', false);
        $this->put(route('financial-information.update'), [
            'report_id' => $report->id, 'project_id' => $report->project_id,
            'financial_year_id' => $report->financial_year_id, 'currency_code' => $report->currency_code,
            'as_of_date' => '2026-10-14', 'annual' => ['total_allocation' => 100],
        ])->assertForbidden();
    }

    public function test_report_editor_can_save_without_create_or_view_permissions(): void
    {
        $this->signIn(['financial-reports.update']);
        $this->seed(PehurProjectSeeder::class);
        $report = FinancialReport::firstOrFail();
        $this->put(route('financial-information.update'), [
            'report_id' => $report->id, 'project_id' => $report->project_id,
            'financial_year_id' => $report->financial_year_id, 'currency_code' => $report->currency_code,
            'as_of_date' => '2026-10-14', 'source_notes' => 'Restricted editor update',
        ])->assertSessionHasNoErrors()->assertRedirect(route('financial-information.edit', $report));
        $this->assertSame('Restricted editor update', $report->fresh()->source_notes);
    }

    public function test_role_editor_only_sees_permissions_they_can_grant_and_cannot_edit_own_role(): void
    {
        $user = $this->signIn(['roles.create', 'roles.update']);
        $forbiddenPermission = Permission::where('code', 'extensions.approve')->sole();
        $this->get(route('roles.create'))->assertOk()
            ->assertDontSee('value="'.$forbiddenPermission->id.'"', false);
        $this->get(route('roles.edit', $user->role_id))->assertForbidden();
        $this->post(route('roles.store'), ['name' => 'Escalated role', 'permissions' => [$forbiddenPermission->id]])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'Escalated role']);
    }

    public function test_pipeline_editor_cannot_add_steps_without_create_permission(): void
    {
        $this->signIn(['projects.update', 'pipeline-steps.update']);
        $this->seed(PipelineTemplateSeeder::class);
        $project = Project::factory()->create(['phase' => 'Pipeline']);
        $template = PipelineStepTemplate::orderBy('sort_order')->firstOrFail();
        $this->get(route('projects.edit', $project))->assertOk()->assertDontSee('data-add-pipeline', false);
        $this->put(route('projects.update', $project), [
            'reference_code' => $project->reference_code, 'name' => $project->name,
            'sector_id' => $project->sector_id, 'district_id' => $project->district_id,
            'phase' => 'Pipeline', 'lifecycle' => 'Active',
            'start_date' => $project->start_date?->toDateString(), 'current_end_date' => $project->current_end_date?->toDateString(),
            'pipeline_components' => [['template_id' => $template->id]],
        ])->assertForbidden();
        $this->assertSame(0, $project->pipelineSteps()->count());
    }

    public function test_login_without_dashboard_permission_opens_account(): void
    {
        $user = $this->signIn([]);
        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasNoErrors()->assertRedirect(route('account.edit'));
    }
}
