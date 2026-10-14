<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\ExtensionRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ComponentExtensionTest extends TestCase
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

    private function assign(Component $component, bool $approve = false): User
    {
        $role = Role::firstOrCreate(['name' => 'Extension team']);
        foreach (['extensions.view', 'extensions.request', 'extensions.approve', 'extensions.withdraw'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code]);
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permission->id]);
        }
        $user = User::factory()->create(['account_status' => 'Active', 'role_id' => $role->id]);
        DB::table('project_assignments')->insert(['project_id' => $component->project_id, 'user_id' => $user->id, 'assignment_role' => 'Project team', 'can_approve_extension' => $approve]);

        return $user;
    }

    public function test_approval_changes_only_effective_date_and_records_decision_audit_and_notification(): void
    {
        $component = Component::factory()->create();
        $requester = $this->assign($component);
        $reviewer = $this->assign($component, true);
        $this->actingAs($requester)->post('/extensions', ['component_id' => $component->id, 'reviewer_id' => $reviewer->id, 'requested_end_date' => '2026-11-01', 'reason' => 'Delivery delay'])->assertRedirect();
        $extension = ExtensionRequest::sole();
        $this->assertSame('2026-10-01', $component->fresh()->effective_end->toDateString());

        $this->actingAs($reviewer)->post('/extensions/'.$extension->id.'/decision', ['decision' => 'Approved', 'remarks' => 'Reviewed delivery plan'])->assertRedirect();

        $this->assertSame('2026-10-01', $component->fresh()->baseline_end->toDateString());
        $this->assertSame('2026-11-01', $component->fresh()->effective_end->toDateString());
        $this->assertSame('Approved', $extension->fresh()->status);
        $this->assertDatabaseHas('extension_decisions', ['request_id' => $extension->id, 'reviewer_id' => $reviewer->id, 'decision' => 'Approved']);
        $this->assertDatabaseHas('notifications', ['recipient_id' => $requester->id, 'event_key' => 'extension:'.$extension->id.':decision']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'components', 'actor_id' => $reviewer->id, 'action' => 'updated']);
        $this->post('/extensions/'.$extension->id.'/decision', ['decision' => 'Approved', 'remarks' => 'Repeat'])->assertSessionHasErrors('record');
        $this->assertDatabaseCount('extension_decisions', 1);
    }

    public function test_sidebar_counts_only_assigned_pending_requests_and_hides_after_decisions(): void
    {
        $component = Component::factory()->create();
        $other = Component::factory()->create(['project_id' => $component->project_id, 'sequence_no' => 2]);
        $requester = $this->assign($component);
        $reviewer = $this->assign($component, true);
        foreach ([$component, $other] as $target) {
            $this->actingAs($requester)->post('/extensions', ['component_id' => $target->id, 'reviewer_id' => $reviewer->id, 'requested_end_date' => '2026-11-01', 'reason' => 'Delivery delay'])->assertSessionHasNoErrors();
        }
        $this->get('/extensions')->assertOk()->assertDontSee('extension-count-badge');
        $this->actingAs($reviewer)->get('/extensions')->assertOk()->assertSee('aria-label="2 pending extension requests"', false);
        $requests = ExtensionRequest::orderBy('id')->get();
        $this->post('/extensions/'.$requests[0]->id.'/decision', ['decision' => 'Approved', 'remarks' => 'Reviewed'])->assertSessionHasNoErrors();
        $this->get('/extensions')->assertOk()->assertSee('aria-label="1 pending extension requests"', false);
        $this->post('/extensions/'.$requests[1]->id.'/decision', ['decision' => 'Rejected', 'remarks' => 'Not required'])->assertSessionHasNoErrors();
        $this->get('/extensions')->assertOk()->assertDontSee('extension-count-badge');
    }

    public function test_request_rejects_self_approval_and_duplicate_pending_requests(): void
    {
        $component = Component::factory()->create();
        $requester = $this->assign($component, true);
        $reviewer = $this->assign($component, true);
        $data = ['component_id' => $component->id, 'reviewer_id' => $requester->id, 'requested_end_date' => '2026-11-01', 'reason' => 'Delivery delay'];
        $this->actingAs($requester)->post('/extensions', $data)->assertSessionHasErrors(['reviewer_id' => 'Select an assigned reviewer with extension approval permission who is neither the requester nor the project director.']);
        $data['reviewer_id'] = $reviewer->id;
        $this->post('/extensions', $data)->assertRedirect();
        $this->post('/extensions', $data)->assertSessionHasErrors('record');
        $this->assertDatabaseCount('extension_requests', 1);
    }

    public function test_rejection_and_withdrawal_leave_the_deadline_unchanged(): void
    {
        $component = Component::factory()->create();
        $requester = $this->assign($component);
        $reviewer = $this->assign($component, true);
        $data = ['component_id' => $component->id, 'reviewer_id' => $reviewer->id, 'requested_end_date' => '2026-11-01', 'reason' => 'Delay'];
        $this->actingAs($requester)->post('/extensions', $data)->assertRedirect();
        $extension = ExtensionRequest::sole();
        $this->actingAs($reviewer)->post('/extensions/'.$extension->id.'/decision', ['decision' => 'Rejected', 'remarks' => 'Insufficient evidence'])->assertRedirect();
        $this->assertSame('2026-10-01', $component->fresh()->effective_end->toDateString());
        $this->actingAs($requester)->post('/extensions', $data)->assertRedirect();
        $next = ExtensionRequest::latest('id')->first();
        $this->post('/extensions/'.$next->id.'/withdraw')->assertRedirect();
        $this->assertSame('Withdrawn', $next->fresh()->status);
        $this->assertSame('2026-10-01', $component->fresh()->effective_end->toDateString());
    }

    public function test_stale_requests_and_unassigned_reviewers_cannot_change_deadlines(): void
    {
        $component = Component::factory()->create();
        $requester = $this->assign($component);
        $reviewer = $this->assign($component, true);
        $this->actingAs($requester)->post('/extensions', ['component_id' => $component->id, 'reviewer_id' => $reviewer->id, 'requested_end_date' => '2026-11-01', 'reason' => 'Delivery delay'])->assertRedirect();
        $extension = ExtensionRequest::sole();
        $this->actingAs(User::factory()->create())->post('/extensions/'.$extension->id.'/decision', ['decision' => 'Approved', 'remarks' => 'Unauthorized'])->assertForbidden();
        $component->update(['version' => 2]);
        $this->actingAs($reviewer)->post('/extensions/'.$extension->id.'/decision', ['decision' => 'Approved', 'remarks' => 'Stale'])->assertSessionHasErrors('record');
        $this->assertSame('Pending', $extension->fresh()->status);
        $this->assertDatabaseCount('extension_decisions', 0);
        $this->assertSame('2026-10-01', $component->fresh()->effective_end->toDateString());
    }

    public function test_subcomponent_extension_is_bounded_by_parent_and_never_extends_it(): void
    {
        $component = Component::factory()->create(['baseline_end' => '2026-12-01', 'effective_end' => '2026-12-01']);
        $subcomponent = $component->subcomponents()->create(['name' => 'Subcomponent', 'sequence_no' => 1, 'baseline_end' => '2026-10-01', 'effective_end' => '2026-10-01', 'lifecycle' => 'Ongoing']);
        $requester = $this->assign($component);
        $reviewer = $this->assign($component, true);
        $data = ['subcomponent_id' => $subcomponent->id, 'reviewer_id' => $reviewer->id, 'requested_end_date' => '2027-01-01', 'reason' => 'Delay'];
        $this->actingAs($requester)->post('/extensions', $data)->assertSessionHasErrors('requested_end_date');
        $data['requested_end_date'] = '2026-11-01';
        $this->post('/extensions', $data)->assertRedirect();
        $this->actingAs($reviewer)->post('/extensions/'.ExtensionRequest::sole()->id.'/decision', ['decision' => 'Approved', 'remarks' => 'Within parent deadline'])->assertRedirect();
        $this->assertSame('2026-11-01', $subcomponent->fresh()->effective_end->toDateString());
        $this->assertSame('2026-12-01', $component->fresh()->effective_end->toDateString());
    }

    public function test_unknown_deadline_cannot_be_extended(): void
    {
        $component = Component::factory()->create(['baseline_end' => null, 'effective_end' => null]);
        $requester = $this->assign($component);
        $reviewer = $this->assign($component, true);
        $this->actingAs($requester)->post('/extensions', ['component_id' => $component->id, 'reviewer_id' => $reviewer->id, 'requested_end_date' => '2026-11-01', 'reason' => 'Delay'])->assertSessionHasErrors('requested_end_date');
        $this->assertDatabaseCount('extension_requests', 0);
    }
}
