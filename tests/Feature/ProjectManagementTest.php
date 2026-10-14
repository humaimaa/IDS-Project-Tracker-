<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\PipelineStepTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineTemplateSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
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

    public function test_pipeline_deadlines_cannot_bypass_extension_approval_through_either_edit_form(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['phase' => 'Pipeline']);
        $template = PipelineStepTemplate::orderBy('sort_order')->firstOrFail();
        $step = $project->pipelineSteps()->create(['template_id' => $template->id, 'name' => $template->name, 'sort_order' => $template->sort_order, 'planned_date' => '2026-11-01', 'status' => 'In progress']);
        $data = ['project_id' => $project->id, 'template_id' => $template->id, 'name' => $step->name, 'status' => 'In progress', 'is_current' => false, 'planned_date' => '2026-12-01'];
        $this->put(route('pipeline-steps.update', $step), $data)->assertSessionHasErrors('planned_date');
        $this->put(route('projects.update', $project), $this->payload($project) + ['pipeline_components' => [['template_id' => $template->id, 'planned_date' => '2026-12-01']]])->assertSessionHasErrors('pipeline_components.0.planned_date');
        $this->assertSame('2026-11-01', $step->fresh()->planned_date->toDateString());
        $data['planned_date'] = '2026-11-01';
        $data['remarks'] = 'Officer updated the details';
        $this->put(route('pipeline-steps.update', $step), $data)->assertSessionHasNoErrors();
        $this->assertSame('Officer updated the details', $step->fresh()->remarks);
    }

    public function test_project_closing_date_cannot_precede_an_unfinished_pipeline_step(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['phase' => 'Pipeline']);
        $template = PipelineStepTemplate::firstOrFail();
        $project->pipelineSteps()->create(['template_id' => $template->id, 'name' => $template->name, 'sort_order' => $template->sort_order, 'planned_date' => '2027-01-01', 'status' => 'In progress']);
        $this->put(route('projects.update', $project), array_replace($this->payload($project), ['current_end_date' => '2026-12-01', 'change_reason' => 'Shorten schedule']))->assertSessionHasErrors('current_end_date');
        $this->assertSame('2027-06-30', $project->fresh()->current_end_date->toDateString());
    }

    public function test_edit_appends_components_and_subcomponents_without_replacing_existing_data(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['phase' => 'Ongoing', 'start_date' => null, 'current_end_date' => null]);
        $component = Component::factory()->create(['project_id' => $project->id, 'sequence_no' => 4, 'name' => 'Existing works', 'start_date' => '2026-01-01', 'baseline_end' => '2027-01-01', 'effective_end' => '2027-01-01']);
        $this->get(route('projects.edit', $project))->assertOk()->assertSee('Existing works')->assertSee('Add subcomponent');
        $this->put(route('projects.update', $project), $this->payload($project) + ['components' => [['id' => $component->id, 'name' => 'Ignored replacement', 'subcomponents' => [['name' => 'New child']]], ['name' => 'New component']]])->assertSessionHasNoErrors()->assertRedirect(route('projects.show', $project));
        $this->assertSame('Existing works', $component->fresh()->name);
        $this->assertSame('2027-01-01', $component->fresh()->effective_end->toDateString());
        $this->assertSame('New child', $component->subcomponents()->sole()->name);
        $this->assertSame(5, $project->components()->where('name', 'New component')->sole()->sequence_no);
    }

    public function test_edit_rejects_a_component_from_another_project(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['phase' => 'Ongoing']);
        $other = Component::factory()->create();
        $this->put(route('projects.update', $project), $this->payload($project) + ['components' => [['id' => $other->id, 'name' => 'Invalid', 'subcomponents' => [['name' => 'Intruder']]]]])->assertSessionHasErrors('components.0.id');
        $this->assertDatabaseMissing('subcomponents', ['name' => 'Intruder']);
    }

    public function test_pipeline_saves_dates_and_officer_and_rejects_repeated_or_reversed_names(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['phase' => 'Pipeline', 'start_date' => null, 'current_end_date' => null]);
        $templates = PipelineStepTemplate::orderBy('sort_order')->get();
        $steps = [['template_id' => $templates[0]->id, 'start_date' => '2026-10-01', 'planned_date' => '2026-11-01', 'responsible_office' => 'Planning officer', 'remarks' => 'Review'], ['template_id' => $templates[1]->id]];
        $this->put(route('projects.update', $project), $this->payload($project) + ['pipeline_components' => $steps])->assertSessionHasNoErrors();
        $step = $project->pipelineSteps()->first();
        $this->assertSame('Planning officer', $step->responsible_office);
        $this->assertSame('2026-10-01', $step->start_date->toDateString());
        $this->assertSame(2, $project->pipelineSteps()->count());
        $this->put(route('projects.update', $project), $this->payload($project) + ['pipeline_components' => [$steps[0], $steps[0]]])->assertSessionHasErrors('pipeline_components.0.template_id');
        $this->put(route('projects.update', $project), $this->payload($project) + ['pipeline_components' => array_reverse($steps)])->assertSessionHasErrors('pipeline_components.1.template_id');
        $this->assertSame(2, $project->pipelineSteps()->count());
    }

    public function test_archive_hides_project_from_active_list_and_restores_with_history(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['name' => 'Archive example']);
        $this->post(route('projects.archive', $project), ['archived' => 1])->assertRedirect();
        $this->assertNotNull($project->fresh()->archived_at);
        $this->get('/projects')->assertDontSee('Archive example');
        $this->get('/projects?archived=1')->assertSee('Archive example')->assertSee('Restore');
        $this->post(route('projects.archive', $project), ['archived' => 0])->assertRedirect();
        $this->assertNull($project->fresh()->archived_at);
        $this->get('/projects')->assertSee('Archive example');
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $project->id, 'module' => 'projects', 'action' => 'updated']);
    }

    public function test_director_cannot_archive_projects_or_edit_other_projects(): void
    {
        $user = $this->signIn('Project Director');
        $project = Project::factory()->create(['project_director_id' => $user->id]);
        $this->post(route('projects.archive', $project), ['archived' => 1])->assertForbidden();
        $other = Project::factory()->create();
        $this->get(route('projects.edit', $other))->assertForbidden();
        $this->assertNull($project->fresh()->archived_at);
    }

    public function test_reference_archiving_retains_data_and_lists_paginate(): void
    {
        $this->signIn();
        foreach (range(1, 27) as $index) {
            Sector::create(['code' => 'S'.$index, 'name' => 'Sector '.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'active' => true]);
        }
        $sector = Sector::where('code', 'S27')->sole();
        $this->get('/sectors')->assertSee('Sector 27')->assertDontSee('Sector 01')->assertSee('page=2', false)->assertSee('data-project-url', false);
        $this->get('/sectors?page=2')->assertSee('Sector 01')->assertDontSee('Sector 27');
        $this->post(route('sectors.archive', $sector->id), ['archived' => 1])->assertRedirect();
        $this->assertFalse($sector->fresh()->active);
        $this->get('/sectors?archived=1')->assertSee('Sector 27')->assertSee('Restore');
        $this->post(route('sectors.archive', $sector->id), ['archived' => 0])->assertRedirect();
        $this->assertTrue($sector->fresh()->active);
    }
}
