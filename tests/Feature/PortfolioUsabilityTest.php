<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\District;
use App\Models\PipelineStepTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PehurProjectSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineTemplateSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PortfolioUsabilityTest extends TestCase
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

    private function signIn(string $role = 'Super Admin'): User
    {
        $this->seed([PermissionSeeder::class, PipelineTemplateSeeder::class]);
        $user = User::factory()->create(['role_id' => Role::where('name', $role)->sole()->id, 'account_status' => 'Active']);
        $this->actingAs($user);

        return $user;
    }

    private function payload(Project $project): array
    {
        return ['reference_code' => $project->reference_code, 'name' => $project->name, 'sector_id' => $project->sector_id, 'district_id' => $project->district_id, 'phase' => $project->phase, 'lifecycle' => 'Active', 'start_date' => $project->start_date?->toDateString(), 'current_end_date' => $project->current_end_date?->toDateString()];
    }

    public function test_multiple_districts_and_text_agency_are_saved_and_filterable(): void
    {
        $this->signIn();
        $project = Project::factory()->create();
        $district = District::create(['code' => 'EXTRA', 'name' => 'Second district', 'active' => true]);
        $data = $this->payload($project);
        unset($data['district_id']);
        $this->put(route('projects.update', $project), $data + ['district_ids' => [$project->district_id, $district->id], 'implementing_agency' => 'Local works office', 'funding_type' => 'Mixed'])->assertSessionHasNoErrors();
        $this->assertCount(2, $project->fresh()->districts);
        $this->assertSame('Local works office', $project->fresh()->implementing_agency);
        $this->get('/projects?district=Second%20district')->assertOk()->assertSee($project->name);
        $this->get(route('projects.show', $project))->assertSee('Second district')->assertSee('Local works office');
        $this->put(route('projects.update', $project), $data + ['district_ids' => [$district->id]])->assertSessionHasNoErrors();
        $this->assertSame([$district->id], $project->fresh()->districts->pluck('id')->all());
        $this->get('/districts')->assertOk()->assertDontSee('<th>Status</th>', false)->assertDontSee('<th>Updated</th>', false);
    }

    public function test_funding_types_and_approval_permissions_are_validated(): void
    {
        $this->signIn();
        $project = Project::factory()->create();
        $user = User::factory()->create(['name' => 'Cannot approve', 'role_id' => Role::where('name', 'Project Director')->sole()->id]);
        $this->get('/projects/create')->assertOk()->assertSee('Govt. share')->assertSee('Mixed')->assertViewHas('extensionApprovers', fn ($users) => ! $users->contains('id', $user->id));
        $this->put(route('projects.update', $project), $this->payload($project) + ['funding_type' => 'Unspecified other'])->assertSessionHasErrors('funding_type');
        $this->put(route('projects.update', $project), $this->payload($project) + ['approvers' => [$user->id]])->assertSessionHasErrors('approvers');
    }

    public function test_pipeline_components_can_be_created_renamed_and_filtered_by_phase(): void
    {
        $this->signIn();
        $pipeline = Project::factory()->create(['phase' => 'Pipeline']);
        $ongoing = Component::factory()->create(['name' => 'Construction works']);
        $template = PipelineStepTemplate::first();
        $data = ['project_id' => $pipeline->id, 'template_id' => $template->id, 'name' => 'Initial feasibility', 'status' => 'Not started', 'is_current' => 1];
        $this->post('/pipeline-steps', $data)->assertSessionHasNoErrors()->assertRedirect();
        $step = $pipeline->pipelineSteps()->sole();
        $data['name'] = 'Revised feasibility';
        $this->put(route('pipeline-steps.update', $step->id), $data)->assertSessionHasNoErrors();
        $this->assertSame('Revised feasibility', $step->fresh()->name);
        $this->get('/pipeline-steps')->assertOk()->assertSee('Revised feasibility')->assertDontSee('Construction works');
        $this->get('/activities')->assertOk()->assertSee('Construction works')->assertDontSee('Revised feasibility');
        $data['project_id'] = $ongoing->project_id;
        $this->post('/pipeline-steps', $data)->assertSessionHasErrors('record');
    }

    public function test_issue_subcomponent_must_belong_to_selected_component_and_project(): void
    {
        $this->signIn();
        $component = Component::factory()->create();
        $sub = $component->subcomponents()->create(['name' => 'Foundation', 'sequence_no' => 1]);
        $other = Component::factory()->create(['project_id' => $component->project_id, 'sequence_no' => 2]);
        $payload = ['project_id' => $component->project_id, 'component_id' => $component->id, 'subcomponent_id' => $sub->id, 'title' => 'Subcomponent issue', 'status' => 'Open'];
        $this->post('/issues', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('issues', ['title' => 'Subcomponent issue', 'component_id' => null, 'subcomponent_id' => $sub->id]);
        $payload['component_id'] = $other->id;
        $this->post('/issues', $payload)->assertSessionHasErrors('subcomponent_id');
        $payload['component_id'] = $component->id;
        $payload['project_id'] = Project::factory()->create()->id;
        $this->post('/issues', $payload)->assertSessionHasErrors('subcomponent_id');
    }

    public function test_pipeline_issue_targets_reject_work_from_another_phase(): void
    {
        $this->signIn();
        $pipeline = Project::factory()->create(['phase' => 'Pipeline']);
        $template = PipelineStepTemplate::first();
        $step = $pipeline->pipelineSteps()->create(['template_id' => $template->id, 'name' => 'Pipeline planning', 'sort_order' => 1]);
        $data = ['project_id' => $pipeline->id, 'pipeline_step_id' => $step->id, 'title' => 'Planning issue', 'status' => 'Open'];
        $this->post('/issues', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('issues', ['title' => 'Planning issue', 'pipeline_step_id' => $step->id]);
        $data['component_id'] = Component::factory()->create()->id;
        $this->post('/issues', $data)->assertSessionHasErrors('component_id');
        unset($data['component_id']);
        $data['project_id'] = Project::factory()->create()->id;
        $this->post('/issues', $data)->assertSessionHasErrors('component_id');
        $this->get('/issues/create')->assertOk()->assertSee('data-issue-component disabled', false)->assertSee('Pipeline planning');
    }

    public function test_financial_overview_precedes_annual_filter_even_without_selected_year_data(): void
    {
        $this->signIn();
        $this->seed(PehurProjectSeeder::class);
        $project = Project::where('reference_code', 'PHLCE-001')->sole();
        $this->get(route('projects.show', [$project, 'tab' => 'financial', 'year' => 999]))->assertOk()->assertSeeInOrder(['Overall project financial information', '15,654.00', 'Annual financial information', 'Financial year'])->assertDontSee('Donor financing')->assertDontSee('74.40');
    }
}
