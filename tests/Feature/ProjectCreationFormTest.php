<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Permission;
use App\Models\PipelineStepTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DistrictSeeder;
use Database\Seeders\PehurProjectSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProjectCreationFormTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portfolio.enabled' => true]);
        Route::setRoutes(new RouteCollection);
        Route::middleware('web')->group(base_path('routes/web.php'));
        Route::getRoutes()->refreshNameLookups();
        $this->seed([PermissionSeeder::class, PehurProjectSeeder::class]);
        $this->actingAs(User::factory()->create(['role_id' => Role::where('name', 'Super Admin')->sole()->id, 'account_status' => 'Active']));
    }

    private function data(string $phase = 'Ongoing'): array
    {
        $project = Project::where('reference_code', 'PHLCE-001')->sole();

        return ['reference_code' => 'NEW-001', 'name' => 'New project', 'phase' => $phase, 'lifecycle' => 'Active', 'sector_id' => $project->sector_id, 'district_id' => $project->district_id, 'start_date' => '2026-01-01', 'current_end_date' => '2027-12-31'];
    }

    public function test_ongoing_project_components_optional_subcomponents_and_cost_save_together(): void
    {
        $data = $this->data() + ['initial_cost' => '1250000', 'cost_currency' => 'PKR', 'components' => [
            ['name' => 'Civil works', 'start_date' => '2026-02-01', 'baseline_end' => '2027-06-30', 'subcomponents' => [['name' => 'Foundation', 'baseline_end' => '2026-12-31'], ['name' => 'Structure']]],
            ['name' => 'Equipment'],
        ]];
        $this->post('/projects', $data)->assertRedirect()->assertSessionHasNoErrors();
        $project = Project::where('reference_code', 'NEW-001')->sole();
        $this->assertSame(2, $project->components()->count());
        $works = $project->components()->where('name', 'Civil works')->sole();
        $this->assertSame(2, $works->subcomponents()->count());
        $this->assertSame(0, $project->components()->where('name', 'Equipment')->sole()->subcomponents()->count());
        $this->assertSame('2027-06-30', $works->effective_end->toDateString());
        $this->assertSame('1250000.00', $project->costVersions()->sole()->total_cost);
        $this->assertSame(0, $project->pipelineSteps()->count());
    }

    public function test_pipeline_uses_predefined_names_and_selected_current_component(): void
    {
        $template = PipelineStepTemplate::where('sort_order', 3)->sole();
        $this->post('/projects', $this->data('Pipeline') + ['pipeline_template_id' => $template->id, 'components' => [['name' => 'Ignored custom component']]])->assertSessionHasNoErrors();
        $project = Project::where('reference_code', 'NEW-001')->sole();
        $this->assertSame(5, $project->pipelineSteps()->count());
        $this->assertSame($template->name, $project->pipelineSteps()->where('is_current', true)->sole()->name);
        $this->assertSame(0, $project->components()->count());
    }

    public function test_invalid_subcomponent_schedule_leaves_no_partial_project(): void
    {
        $this->post('/projects', $this->data() + ['components' => [['name' => 'Parent', 'baseline_end' => '2026-06-01', 'subcomponents' => [['name' => 'Too late', 'baseline_end' => '2026-07-01']]]]])->assertSessionHasErrors('components.0.subcomponents.0.baseline_end');
        $this->assertDatabaseMissing('projects', ['reference_code' => 'NEW-001']);
        $this->assertDatabaseMissing('components', ['name' => 'Parent']);
    }

    public function test_inactive_pipeline_template_and_unauthorized_nested_creation_are_rejected(): void
    {
        $template = PipelineStepTemplate::first();
        $template->update(['active' => false]);
        $this->post('/projects', $this->data('Pipeline') + ['pipeline_template_id' => $template->id])->assertSessionHasErrors('pipeline_template_id');
        $permission = Permission::where('code', 'subcomponents.create')->sole();
        DB::table('role_permissions')->where('role_id', auth()->user()->role_id)->where('permission_id', $permission->id)->delete();
        $this->post('/projects', $this->data() + ['components' => [['name' => 'Parent', 'subcomponents' => [['name' => 'Forbidden']]]]])->assertForbidden();
        $this->assertDatabaseMissing('projects', ['reference_code' => 'NEW-001']);
    }

    public function test_district_seeder_adds_36_and_preserves_multi_district_on_rerun(): void
    {
        $this->seed(DistrictSeeder::class);
        $this->seed(DistrictSeeder::class);
        $this->assertSame(36, District::where('code', 'like', 'KP-%')->count());
        $this->assertDatabaseHas('districts', ['name' => 'Multi-district']);
        $this->assertDatabaseHas('districts', ['name' => 'Lower South Waziristan']);
        $this->get('/projects/create')->assertOk()->assertSee('Abbottabad')->assertSee('Pipeline components')->assertSee('Add subcomponent (optional)');
        $this->get('/sectors')->assertOk()->assertSee('Add sector');
        $this->get('/partners')->assertOk()->assertSee('Add development partner');
    }
}
