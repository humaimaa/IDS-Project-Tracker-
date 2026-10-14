<?php

namespace Tests\Feature;

use App\Models\ExtensionRequest;
use App\Models\PipelineStepTemplate;
use App\Models\Project;
use App\Models\ProjectPipelineStep;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PipelineExtensionTest extends TestCase
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

    /** @return array{ProjectPipelineStep, User, User, array<string, mixed>} */
    private function scenario(): array
    {
        $this->seed(PermissionSeeder::class);
        $requester = User::factory()->create(['role_id' => Role::where('name', 'Project Director')->sole()->id, 'account_status' => 'Active']);
        $reviewer = User::factory()->create(['role_id' => Role::where('name', 'Super Admin')->sole()->id, 'account_status' => 'Active']);
        $project = Project::factory()->create(['phase' => 'Pipeline', 'project_director_id' => $requester->id]);
        $template = PipelineStepTemplate::create(['name' => 'Feasibility review', 'sort_order' => 1, 'active' => true]);
        $step = ProjectPipelineStep::create(['project_id' => $project->id, 'template_id' => $template->id, 'name' => $template->name, 'sort_order' => 1, 'status' => 'In progress', 'is_current' => true, 'planned_date' => '2026-10-01']);
        DB::table('project_assignments')->insert(['project_id' => $project->id, 'user_id' => $reviewer->id, 'assignment_role' => 'Extension approver', 'can_approve_extension' => true]);
        $this->actingAs($requester);

        return [$step, $requester, $reviewer, ['pipeline_step_id' => $step->id, 'reviewer_id' => $reviewer->id, 'requested_end_date' => '2026-11-01', 'reason' => 'Additional feasibility review time']];
    }

    public function test_pipeline_request_shows_on_project_and_review_list_and_approval_updates_only_step(): void
    {
        [$step, $requester, $reviewer, $data] = $this->scenario();
        $this->get(route('projects.show', ['project' => $step->project_id, 'tab' => 'pipeline']))->assertOk()->assertSee('name="pipeline_step_id"', false);
        $this->post('/extensions', $data)->assertSessionHasNoErrors()->assertRedirect(route('projects.show', ['project' => $step->project_id, 'tab' => 'pipeline']));
        $extension = ExtensionRequest::sole();
        $this->assertSame('2026-10-01', $step->fresh()->planned_date->toDateString());
        $this->get(route('projects.show', ['project' => $step->project_id, 'tab' => 'pipeline']))->assertSee($data['reason']);
        $this->actingAs($reviewer)->get('/extensions')->assertOk()->assertSee($step->name)->assertSee('1 pending extension requests');
        $this->post('/extensions/'.$extension->id.'/decision', ['decision' => 'Approved', 'remarks' => 'Reviewed'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-01', $step->fresh()->planned_date->toDateString());
        $this->assertSame('2027-06-30', $step->project->current_end_date->toDateString());
        $this->assertSame('2026-10-01', $extension->fresh()->old_end_date->toDateString());
        $this->assertDatabaseHas('extension_decisions', ['request_id' => $extension->id, 'decision' => 'Approved']);
        $this->assertDatabaseHas('notifications', ['recipient_id' => $requester->id, 'event_key' => 'extension:'.$extension->id.':decision']);
        $this->get('/extensions')->assertDontSee('extension-count-badge');
    }

    public function test_pipeline_request_rejects_invalid_dates_completed_steps_wrong_phase_and_duplicates(): void
    {
        [$step, $requester, $reviewer, $data] = $this->scenario();
        foreach (['2026-10-01', '2028-01-01'] as $date) {
            $this->post('/extensions', array_replace($data, ['requested_end_date' => $date]))->assertSessionHasErrors('requested_end_date');
        }
        $step->update(['status' => 'Done']);
        $this->post('/extensions', $data)->assertSessionHasErrors('requested_end_date');
        $step->update(['status' => 'In progress', 'planned_date' => null]);
        $this->post('/extensions', $data)->assertSessionHasErrors('requested_end_date');
        $step->update(['planned_date' => '2026-10-01']);
        $step->project->update(['phase' => 'Ongoing']);
        $this->post('/extensions', $data)->assertSessionHasErrors('record');
        $step->project->update(['phase' => 'Pipeline']);
        $this->post('/extensions', $data)->assertSessionHasNoErrors();
        $this->post('/extensions', $data)->assertSessionHasErrors('record');
        $this->assertDatabaseCount('extension_requests', 1);
    }

    public function test_pipeline_review_rejects_own_project_unassigned_users_and_stale_requests(): void
    {
        [$step, $requester, $reviewer, $data] = $this->scenario();
        $this->post('/extensions', array_replace($data, ['reviewer_id' => $requester->id]))->assertSessionHasErrors('reviewer_id');
        $this->post('/extensions', $data)->assertSessionHasNoErrors();
        $extension = ExtensionRequest::sole();
        $this->post('/extensions/'.$extension->id.'/decision', ['decision' => 'Approved', 'remarks' => 'Own project'])->assertForbidden();
        $step->update(['remarks' => 'Schedule under review']);
        $this->actingAs($reviewer)->post('/extensions/'.$extension->id.'/decision', ['decision' => 'Approved', 'remarks' => 'Stale'])->assertSessionHasErrors('record');
        $this->assertSame('2026-10-01', $step->fresh()->planned_date->toDateString());
        $this->post('/extensions/'.$extension->id.'/decision', ['decision' => 'Returned', 'remarks' => 'Resubmit'])->assertSessionHasNoErrors();
        $this->assertSame('Returned', $extension->fresh()->status);
    }

    public function test_pipeline_rejection_and_withdrawal_preserve_deadline(): void
    {
        [$step, $requester, $reviewer, $data] = $this->scenario();
        $this->post('/extensions', $data)->assertSessionHasNoErrors();
        $extension = ExtensionRequest::sole();
        $this->actingAs($reviewer)->post('/extensions/'.$extension->id.'/decision', ['decision' => 'Rejected', 'remarks' => 'Not needed'])->assertSessionHasNoErrors();
        $this->actingAs($requester)->post('/extensions', $data)->assertSessionHasNoErrors();
        $this->post('/extensions/'.ExtensionRequest::latest('id')->first()->id.'/withdraw')->assertSessionHasNoErrors();
        $this->assertSame('2026-10-01', $step->fresh()->planned_date->toDateString());
        $this->actingAs($reviewer)->get('/extensions')->assertDontSee('extension-count-badge');
    }

    public function test_database_rejects_duplicate_pending_pipeline_requests(): void
    {
        [$step, $requester, $reviewer, $data] = $this->scenario();
        $this->post('/extensions', $data)->assertSessionHasNoErrors();
        $this->expectException(QueryException::class);
        ExtensionRequest::create($data + ['requester_id' => $requester->id, 'old_end_date' => '2026-10-01', 'target_version' => 1, 'status' => 'Pending']);
    }

    public function test_request_ignores_forged_approval_fields_and_escapes_reason(): void
    {
        [$step, $requester, $reviewer, $data] = $this->scenario();
        $data['reason'] = '<script>alert("qa")</script>';
        $this->post('/extensions', $data + ['requester_id' => $reviewer->id, 'status' => 'Approved', 'target_version' => 999, 'old_end_date' => '2020-01-01'])->assertSessionHasNoErrors();
        $extension = ExtensionRequest::sole();
        $this->assertSame('Pending', $extension->status);
        $this->assertSame($requester->id, $extension->requester_id);
        $this->assertSame($step->version, $extension->target_version);
        $this->assertSame('2026-10-01', $extension->old_end_date->toDateString());
        $this->actingAs($reviewer)->get('/extensions')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee($data['reason'], false);
    }

    public function test_database_preserves_target_and_reviewer_constraints(): void
    {
        [$step, $requester, $reviewer, $data] = $this->scenario();
        $this->expectException(QueryException::class);
        ExtensionRequest::create($data + ['requester_id' => $reviewer->id, 'old_end_date' => '2026-10-01', 'target_version' => 1, 'status' => 'Pending']);
    }
}
