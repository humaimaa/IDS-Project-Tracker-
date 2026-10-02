<?php

namespace Tests\Feature;

use App\Models\TrackerRecord;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActivityManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sidebar_groups_subactivity_management_and_displays_the_user_name(): void
    {
        $this->get('/subactivities')
            ->assertOk()
            ->assertSee('Manage sub-activities')
            ->assertSee('Add sub-activity')
            ->assertSee('Aina Khan')
            ->assertDontSee('List sub-activities')
            ->assertDontSee('action=edit');
    }

    public function test_subactivities_have_separate_crud_pages_and_keep_the_selected_parent(): void
    {
        $project = TrackerRecord::factory()->create();
        $parent = TrackerRecord::factory()->create(['module' => 'activities', 'data' => ['name' => 'Planning', 'type' => 'Custom', 'project_id' => (string) $project->id, 'project' => $project->data['name'], 'stage' => 'Concept', 'status' => 'Not Started']]);
        $values = ['name' => 'Site survey', 'type' => 'Custom', 'project_id' => (string) $project->id, 'parent_id' => (string) $parent->id, 'stage' => 'Concept', 'status' => 'Not Started', 'officer' => 'Project officer'];
        $this->get('/subactivities/create')->assertOk()->assertSee('Parent activity')->assertSee('Predefined')->assertSee('Custom');

        $this->post('/subactivities', $values)->assertSessionHasNoErrors();

        $child = TrackerRecord::where('module', 'subactivities')->sole();
        $this->assertSame((string) $parent->id, $child->data['parent_id']);
        $this->assertSame('Planning', $child->data['parent_activity']);
        $this->get('/subactivities')->assertOk()->assertSee('Site survey');
        $this->get(route('subactivities.show', $child))->assertOk()->assertSee('Planning');
        $this->get(route('subactivities.edit', $child))->assertOk()->assertSee('Project officer');
        $this->put(route('subactivities.update', $child), array_replace($values, ['name' => 'Updated survey']))->assertSessionHasNoErrors();
        $this->assertSame('Updated survey', $child->fresh()->data['name']);
        $this->get(route('subactivities.delete', $child))->assertOk()->assertSee('This action cannot be undone.');
        $this->delete(route('subactivities.destroy', $child))->assertRedirect(route('subactivities.index'));
        $this->assertModelMissing($child);
    }

    public function test_parent_must_belong_to_the_selected_project_and_stage(): void
    {
        $first = TrackerRecord::factory()->create();
        $other = TrackerRecord::factory()->create();
        $parent = TrackerRecord::factory()->create(['module' => 'activities', 'data' => ['name' => 'Planning', 'project_id' => (string) $first->id, 'stage' => 'Concept']]);
        $values = ['name' => 'Survey', 'type' => 'Custom', 'project_id' => (string) $other->id, 'parent_id' => (string) $parent->id, 'stage' => 'Concept', 'status' => 'Not Started'];

        $this->post('/subactivities', $values)->assertSessionHasErrors(['parent_id' => 'Choose a parent activity in the selected project and stage.']);
        $this->post('/subactivities', array_replace($values, ['project_id' => (string) $first->id, 'stage' => 'Implementation']))->assertSessionHasErrors('parent_id');
        $this->post('/subactivities', array_replace($values, ['parent_id' => '']))->assertSessionHasErrors('parent_id');
        $this->post('/subactivities', array_replace($values, ['parent_id' => (string) $first->id]))->assertSessionHasErrors('parent_id');

        $this->assertSame(0, TrackerRecord::where('module', 'subactivities')->count());
    }

    public function test_parent_cannot_be_deleted_or_moved_while_children_exist(): void
    {
        $this->post('/projects', TrackerRecord::factory()->make()->data)->assertSessionHasNoErrors();
        $parent = TrackerRecord::where('module', 'activities')->first();
        $other = TrackerRecord::factory()->create();

        $this->from(route('activities.delete', $parent))->delete(route('activities.destroy', $parent))->assertSessionHasErrors('parent_id');
        $this->put(route('activities.update', $parent), array_replace($parent->data, ['project_id' => (string) $other->id]))->assertSessionHasErrors('project_id');

        $this->assertModelExists($parent);
        $this->assertNotSame((string) $other->id, $parent->fresh()->data['project_id']);
        $this->assertSame(12, TrackerRecord::where('module', 'subactivities')->count());
    }

    public function test_predefined_child_is_selected_by_template_and_can_be_edited_and_deleted(): void
    {
        $project = TrackerRecord::factory()->create();
        $parent = TrackerRecord::factory()->create(['module' => 'activities', 'data' => ['name' => 'Prepare concept note', 'type' => 'Predefined', 'template_id' => 'concept-note', 'project_id' => (string) $project->id, 'stage' => 'Concept']]);
        $values = ['type' => 'Predefined', 'template_id' => 'identify-needs', 'project_id' => (string) $project->id, 'parent_id' => (string) $parent->id, 'stage' => 'Concept', 'status' => 'Not Started'];

        $this->post('/subactivities', $values)->assertSessionHasNoErrors();

        $child = TrackerRecord::where('module', 'subactivities')->sole();
        $this->assertSame('Identify project needs', $child->data['name']);
        $this->post('/subactivities', $values)->assertSessionHasErrors('template_id');
        $this->put(route('subactivities.update', $child), array_replace($values, ['status' => 'Completed']))->assertSessionHasNoErrors();
        $this->assertSame('Completed', $child->fresh()->data['status']);
        $this->delete(route('subactivities.destroy', $child))->assertRedirect(route('subactivities.index'));
        $this->assertModelMissing($child);
    }

    public function test_custom_subactivity_requires_a_name_and_rejects_template_and_reversed_dates(): void
    {
        $project = TrackerRecord::factory()->create();
        $parent = TrackerRecord::factory()->create(['module' => 'activities', 'data' => ['name' => 'Planning', 'project_id' => (string) $project->id, 'stage' => 'Concept']]);
        $values = ['type' => 'Custom', 'project_id' => (string) $project->id, 'parent_id' => (string) $parent->id, 'stage' => 'Concept', 'status' => 'Not Started'];

        $this->post('/subactivities', $values)->assertSessionHasErrors('name');
        $this->post('/subactivities', array_replace($values, ['name' => 'Survey', 'template_id' => 'identify-needs']))->assertSessionHasErrors('template_id');
        $this->post('/subactivities', array_replace($values, ['name' => 'Survey', 'start' => '2026-10-20', 'due' => '2026-10-01']))->assertSessionHasErrors('due');

        $this->assertSame(0, TrackerRecord::where('module', 'subactivities')->count());
    }

    public function test_activity_lists_filter_by_type_project_and_stage(): void
    {
        $this->post('/projects', TrackerRecord::factory()->make()->data)->assertSessionHasNoErrors();
        $project = TrackerRecord::where('module', 'projects')->sole();
        $this->post('/activities', ['type' => 'Custom', 'project_id' => (string) $project->id, 'name' => 'Special survey', 'stage' => 'Concept', 'status' => 'Not Started'])->assertSessionHasNoErrors();

        $this->get(route('activities.index', ['project_id' => $project->id, 'type' => 'Custom', 'stage' => 'Concept']))->assertOk()->assertSee('Special survey')->assertDontSee('Prepare concept note');
        $this->get(route('activities.index', ['project_id' => $project->id, 'type' => 'Predefined', 'stage' => 'Concept']))->assertOk()->assertSee('Prepare concept note')->assertDontSee('Special survey')->assertDontSee('Procurement and mobilization');
    }

    public function test_subactivity_documents_are_retained_on_edit(): void
    {
        Storage::fake('local');
        $this->post('/projects', TrackerRecord::factory()->make()->data)->assertSessionHasNoErrors();
        $child = TrackerRecord::where('module', 'subactivities')->first();
        $values = array_replace($child->data, ['documents' => [UploadedFile::fake()->create('Survey.pdf', 10, 'application/pdf')]]);

        $this->put(route('subactivities.update', $child), $values)->assertSessionHasNoErrors();

        $attachment = $child->fresh()->data['attachments'][0];
        Storage::disk('local')->assertExists($attachment['path']);
        unset($values['documents']);
        $this->put(route('subactivities.update', $child), $values)->assertSessionHasNoErrors();
        $this->assertSame($attachment, $child->fresh()->data['attachments'][0]);
    }

    public function test_migration_preserves_existing_nested_work_and_progress(): void
    {
        $project = TrackerRecord::factory()->create(['data' => ['name' => 'Existing project', 'stage' => 'Concept', 'activities' => [
            ['name' => 'Legacy planning', 'stage' => 'Concept', 'status' => 'Completed', 'subactivities' => [['name' => 'Legacy survey', 'status' => 'In Progress', 'remarks' => 'Keep this progress']]],
            ['template_id' => 'concept-note', 'name' => 'Prepare concept note', 'stage' => 'Concept', 'status' => 'In Progress', 'subactivities' => [['template_id' => 'identify-needs', 'name' => 'Identify project needs', 'status' => 'Completed']]],
        ]]]);
        $migration = require database_path('migrations/2026_10_01_124352_separate_project_activities_into_tracker_records.php');

        $migration->up();

        $this->assertArrayNotHasKey('activities', $project->fresh()->data);
        $parent = TrackerRecord::where('module', 'activities')->where('data->name', 'Legacy planning')->sole();
        $child = TrackerRecord::where('module', 'subactivities')->where('data->name', 'Legacy survey')->sole();
        $this->assertSame('Completed', $parent->data['status']);
        $this->assertSame((string) $parent->id, $child->data['parent_id']);
        $this->assertSame('Keep this progress', $child->data['remarks']);
        $this->assertSame(7, TrackerRecord::where('module', 'activities')->count());
        $this->assertSame(13, TrackerRecord::where('module', 'subactivities')->count());
        $predefined = TrackerRecord::where('module', 'subactivities')->where('data->template_id', 'identify-needs')->sole();
        $this->assertSame('Completed', $predefined->data['status']);
    }
}
