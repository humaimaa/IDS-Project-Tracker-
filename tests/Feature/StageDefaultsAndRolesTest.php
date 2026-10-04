<?php

namespace Tests\Feature;

use App\Models\TrackerRecord;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StageDefaultsAndRolesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_defaults_cover_all_stages_and_custom_work_and_progress_stay_in_one_project(): void
    {
        $this->post('/projects', TrackerRecord::factory()->make()->data)->assertSessionHasNoErrors();
        $project = TrackerRecord::where('module', 'projects')->sole();
        $activities = TrackerRecord::where('module', 'activities')->get();
        $this->assertSame(['Concept', 'PC-I Development', 'Implementation'], $activities->pluck('data.stage')->unique()->values()->all());
        $this->assertCount(21, $activities);
        $this->assertSame(12, TrackerRecord::where('module', 'subactivities')->count());
        $activity = $activities->first();
        $this->put(route('activities.update', $activity), array_replace($activity->data, ['status' => 'Completed']))->assertSessionHasNoErrors();
        $this->post('/subactivities', ['type' => 'Custom', 'name' => 'Local consultation', 'project_id' => (string) $project->id, 'parent_id' => (string) $activity->id, 'stage' => 'Concept', 'status' => 'In Progress'])->assertSessionHasNoErrors();

        $this->post('/projects', TrackerRecord::factory()->make()->data)->assertSessionHasNoErrors();

        $other = TrackerRecord::where('module', 'projects')->latest('id')->first();
        $this->assertSame('Completed', $activity->fresh()->data['status']);
        $this->get(route('subactivities.index', ['project_id' => $other->id]))->assertDontSee('Local consultation');
        $this->assertSame('Not Started', TrackerRecord::where('module', 'activities')->where('data->project_id', (string) $other->id)->first()->data['status']);
    }

    public function test_predefined_names_cannot_be_overridden(): void
    {
        $project = TrackerRecord::factory()->create();

        $this->post('/activities', ['type' => 'Predefined', 'template_id' => 'concept-note', 'name' => 'Changed title', 'project_id' => (string) $project->id, 'stage' => 'Concept', 'status' => 'Completed'])->assertSessionHasNoErrors();

        $activity = TrackerRecord::where('module', 'activities')->sole();
        $this->assertSame('Activity 1', $activity->data['name']);
        $this->assertSame('Completed', $activity->data['status']);
    }

    public function test_project_updates_preserve_saved_activity_progress(): void
    {
        $this->post('/projects', TrackerRecord::factory()->make()->data)->assertSessionHasNoErrors();
        $project = TrackerRecord::where('module', 'projects')->sole();
        $activity = TrackerRecord::where('module', 'activities')->first();
        $this->put(route('activities.update', $activity), array_replace($activity->data, ['status' => 'Completed']))->assertSessionHasNoErrors();

        $this->put(route('projects.update', $project), $project->data)->assertSessionHasNoErrors();

        $this->assertSame('Completed', $activity->fresh()->data['status']);
    }

    public function test_predefined_activities_cannot_be_moved_to_another_stage(): void
    {
        $project = TrackerRecord::factory()->create();

        $this->post('/activities', ['type' => 'Predefined', 'template_id' => 'concept-note', 'project_id' => (string) $project->id, 'stage' => 'Implementation', 'status' => 'Not Started'])->assertSessionHasErrors('template_id');

        $this->assertSame(0, TrackerRecord::where('module', 'activities')->count());
    }

    public function test_predefined_children_cannot_be_attached_to_another_parent(): void
    {
        $this->post('/projects', TrackerRecord::factory()->make()->data)->assertSessionHasNoErrors();
        $project = TrackerRecord::where('module', 'projects')->sole();
        $activity = TrackerRecord::where('module', 'activities')->first();

        $this->post('/subactivities', ['type' => 'Predefined', 'template_id' => 'procurement-plan', 'parent_id' => (string) $activity->id, 'project_id' => (string) $project->id, 'stage' => 'Concept', 'status' => 'Not Started'])->assertSessionHasErrors('template_id');

        $this->assertSame(12, TrackerRecord::where('module', 'subactivities')->count());
    }

    public function test_predefined_activity_cannot_be_submitted_twice(): void
    {
        $this->post('/projects', TrackerRecord::factory()->make()->data)->assertSessionHasNoErrors();
        $activity = TrackerRecord::where('module', 'activities')->first();

        $this->post('/activities', $activity->data)->assertSessionHasErrors('template_id');

        $this->assertSame(21, TrackerRecord::where('module', 'activities')->count());
    }

    public function test_user_role_must_exist_and_inherited_permissions_follow_role_changes(): void
    {
        $values = ['name' => 'Officer', 'email' => 'officer@example.org', 'role' => 'Reviewer', 'status' => 'Active'];
        $this->post('/users', $values)->assertSessionHasErrors('role');
        $this->assertDatabaseCount('tracker_records', 0);
        $this->get('/users/create')->assertSee('Create a role first');
        $this->post('/roles', ['name' => 'Reviewer', 'permissions' => ['View projects']])->assertSessionHasNoErrors();
        $role = TrackerRecord::where('module', 'roles')->sole();
        $this->post('/users', $values)->assertSessionHasNoErrors();
        $user = TrackerRecord::where('module', 'users')->sole();
        $this->get(route('users.show', $user))->assertSee('View projects');

        $this->put(route('roles.update', $role), ['name' => 'Senior reviewer', 'permissions' => ['View projects', 'Edit projects']])->assertSessionHasNoErrors();

        $this->assertSame('Senior reviewer', $user->fresh()->data['role']);
        $this->get(route('users.show', $user))->assertSee('Edit projects')->assertSee('Senior reviewer');
    }

    public function test_assigned_role_cannot_be_deleted(): void
    {
        $role = TrackerRecord::factory()->create(['module' => 'roles', 'data' => ['name' => 'Reviewer']]);
        TrackerRecord::factory()->create(['module' => 'users', 'data' => ['name' => 'Officer', 'role' => 'Reviewer']]);

        $this->from(route('roles.delete', $role))->delete(route('roles.destroy', $role))->assertSessionHasErrors('role');

        $this->assertModelExists($role);
    }

    public function test_role_names_are_unique_and_unknown_permissions_are_rejected(): void
    {
        $role = TrackerRecord::factory()->create(['module' => 'roles', 'data' => ['name' => 'Reviewer', 'permissions' => ['View projects']]]);

        $this->post('/roles', ['name' => 'Reviewer'])->assertSessionHasErrors('name');
        $this->put(route('roles.update', $role), ['name' => 'Reviewer', 'permissions' => ['Unknown permission']])->assertSessionHasErrors('permissions.0');

        $this->assertSame(['View projects'], $role->fresh()->data['permissions']);
        $this->assertDatabaseCount('tracker_records', 1);
    }

    public function test_all_role_permissions_can_be_cleared(): void
    {
        $role = TrackerRecord::factory()->create(['module' => 'roles', 'data' => ['name' => 'Reviewer', 'permissions' => ['View projects']]]);

        $this->put(route('roles.update', $role), ['name' => 'Reviewer'])->assertSessionHasNoErrors();

        $this->assertSame([], $role->fresh()->data['permissions']);
    }
}
