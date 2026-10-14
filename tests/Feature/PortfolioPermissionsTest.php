<?php

namespace Tests\Feature;

use App\EmailChangeMailer;
use App\Models\Component;
use App\Models\ExtensionRequest;
use App\Models\Issue;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PortfolioPermissionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portfolio.enabled' => true]);
        Route::setRoutes(new RouteCollection);
        Route::middleware('web')->group(base_path('routes/web.php'));
        Route::getRoutes()->refreshNameLookups();
        $this->seed(PermissionSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->sole()->id, 'account_status' => 'Active']);
    }

    public function test_seeded_accounts_have_distinct_access_and_reruns_do_not_reset_passwords(): void
    {
        $this->seed(UserSeeder::class);
        $super = User::where('email', 'superadmin@ids.local')->sole();
        $admin = User::where('email', 'admin@ids.local')->sole();
        $this->assertTrue(Hash::check('SuperAdmin@12345', $super->password));
        $this->assertTrue($super->hasPortfolioPermission('roles.create'));
        $this->assertFalse($admin->hasPortfolioPermission('roles.create'));
        $this->assertTrue($admin->hasPortfolioPermission('issues.update'));
        $admin->update(['password' => 'ChangedPassword123!']);
        $this->seed(UserSeeder::class);
        $this->assertDatabaseCount('users', 2);
        $this->assertTrue(Hash::check('ChangedPassword123!', $admin->fresh()->password));
    }

    public function test_get_started_opens_standalone_login_and_guests_cannot_open_workspace(): void
    {
        $user = $this->user('Admin');
        $this->get('/')->assertOk()->assertSee(route('login'), false);
        $this->get('/login')->assertOk()->assertSee('Log in')->assertDontSee('id="sidebar"', false)->assertDontSee('app-header', false);
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/projects')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_director_only_sees_owned_project_issues_and_cannot_write_to_other_projects(): void
    {
        $director = $this->user('Project Director');
        $own = Project::factory()->create(['project_director_id' => $director->id, 'name' => 'Owned project']);
        $other = Project::factory()->create(['name' => 'Restricted project']);
        $ownIssue = Issue::create(['project_id' => $own->id, 'title' => 'Owned issue', 'status' => 'Open']);
        $foreignIssue = Issue::create(['project_id' => $other->id, 'title' => 'Restricted issue', 'status' => 'Open']);
        $this->actingAs($director)->get('/issues')->assertOk()->assertSee('Owned issue')->assertDontSee('Restricted issue');
        $this->get('/issues?project_id='.$other->id)->assertOk()->assertDontSee('Restricted issue');
        $this->get('/issues/'.$foreignIssue->id)->assertNotFound();
        $this->get('/issues/'.$ownIssue->id)->assertOk();
        $this->get('/issues/create')->assertOk()->assertSee('Owned project')->assertDontSee('Restricted project');
        $this->get('/dashboard')->assertOk()->assertSee('Owned project')->assertDontSee('Restricted project');
        $this->get('/projects/'.$other->id)->assertForbidden();
        $this->post('/issues', ['project_id' => $other->id, 'title' => 'Injected issue', 'status' => 'Open'])->assertForbidden();
        $this->assertDatabaseMissing('issues', ['title' => 'Injected issue']);
        $this->post('/issues', ['project_id' => $own->id, 'title' => 'Allowed issue', 'status' => 'Open'])->assertRedirect();
        $this->assertDatabaseHas('issues', ['project_id' => $own->id, 'title' => 'Allowed issue']);
    }

    public function test_director_can_create_flagship_project_and_cannot_choose_another_owner(): void
    {
        $director = $this->user('Project Director');
        $existing = Project::factory()->create();
        $other = $this->user('Admin');
        $this->actingAs($director)->get('/projects/create')->assertOk()->assertSee('Flagship project')->assertDontSee('name="project_director_id"', false);
        $this->post('/projects', ['reference_code' => 'PD-001', 'name' => 'Flagship by director', 'phase' => 'Ongoing', 'lifecycle' => 'Active', 'sector_id' => $existing->sector_id, 'district_id' => $existing->district_id, 'is_flagship' => 1, 'project_director_id' => $other->id])->assertRedirect();
        $this->assertDatabaseHas('projects', ['reference_code' => 'PD-001', 'project_director_id' => $director->id, 'is_flagship' => true]);
    }

    public function test_read_only_permissions_hide_actions_and_reject_direct_requests(): void
    {
        $role = Role::create(['name' => 'Read only']);
        foreach (['projects.view', 'issues.view'] as $code) {
            DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission_id' => Permission::where('code', $code)->sole()->id]);
        }
        $user = User::factory()->create(['role_id' => $role->id, 'account_status' => 'Active']);
        $project = Project::factory()->create();
        $this->actingAs($user)->get('/projects')->assertOk()->assertDontSee('href="'.route('projects.create').'"', false);
        $this->get('/projects/'.$project->id)->assertOk()->assertDontSee('Edit general information')->assertDontSee('Attach document')->assertDontSee('data-stored-chat-open', false);
        $this->get('/issues')->assertOk()->assertDontSee('href="'.route('issues.create').'"', false);
        $this->get('/projects/create')->assertForbidden();
        $this->post('/issues', ['project_id' => $project->id, 'title' => 'Not allowed', 'status' => 'Open'])->assertForbidden();
    }

    public function test_admin_cannot_grant_super_admin_or_modify_super_admin_accounts(): void
    {
        $admin = $this->user('Admin');
        $super = $this->user('Super Admin');
        $this->actingAs($admin)->get('/roles/create')->assertForbidden();
        $this->post('/users', ['name' => 'Escalation', 'email' => 'escalation@example.test', 'password' => 'TestingPassword123!', 'role_id' => $super->role_id, 'account_status' => 'Active'])->assertForbidden();
        $this->put('/users/'.$super->id, ['name' => 'Hijacked', 'email' => $super->email, 'password' => 'TestingPassword123!', 'role_id' => $admin->role_id, 'account_status' => 'Active'])->assertForbidden();
        $this->delete('/users/'.$super->id)->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'escalation@example.test']);
        $this->assertSame('Super Admin', $super->fresh()->role->name);
    }

    public function test_approval_permission_never_allows_own_project_even_when_requester_is_someone_else(): void
    {
        $owner = $this->user('Super Admin');
        $requester = $this->user('Admin');
        $component = Component::factory()->create();
        $component->project->forceFill(['project_director_id' => $owner->id])->save();
        DB::table('project_assignments')->insert(['project_id' => $component->project_id, 'user_id' => $owner->id, 'assignment_role' => 'Project team', 'can_approve_extension' => true]);
        $this->actingAs($requester)->post('/extensions', ['component_id' => $component->id, 'reviewer_id' => $owner->id, 'requested_end_date' => '2026-11-01', 'reason' => 'Delay'])->assertSessionHasErrors('reviewer_id');
        $extension = ExtensionRequest::create(['component_id' => $component->id, 'requester_id' => $requester->id, 'reviewer_id' => $owner->id, 'old_end_date' => '2026-10-01', 'requested_end_date' => '2026-11-01', 'reason' => 'Previously assigned', 'target_version' => $component->fresh()->version, 'status' => 'Pending']);
        $this->actingAs($owner)->post('/extensions/'.$extension->id.'/decision', ['decision' => 'Approved', 'remarks' => 'Attempt'])->assertForbidden();
        $this->get('/projects/'.$component->project_id.'?tab=physical')->assertOk()->assertDontSee('Record decision');
        $this->assertSame('Pending', $extension->fresh()->status);
        $this->assertSame('2026-10-01', $component->fresh()->effective_end->toDateString());
    }

    public function test_assignment_without_approval_permission_is_not_sufficient(): void
    {
        $requester = $this->user('Admin');
        $reviewer = $this->user('Project Director');
        $component = Component::factory()->create();
        DB::table('project_assignments')->insert(['project_id' => $component->project_id, 'user_id' => $reviewer->id, 'assignment_role' => 'Project team', 'can_approve_extension' => true]);
        $this->actingAs($requester)->post('/extensions', ['component_id' => $component->id, 'reviewer_id' => $reviewer->id, 'requested_end_date' => '2026-11-01', 'reason' => 'Delay'])->assertSessionHasErrors('reviewer_id');
        $this->assertDatabaseCount('extension_requests', 0);
    }

    public function test_director_with_approval_permission_can_review_another_projects_extension_without_seeing_its_issues(): void
    {
        $requester = $this->user('Admin');
        $reviewer = $this->user('Project Director');
        DB::table('role_permissions')->insert(['role_id' => $reviewer->role_id, 'permission_id' => Permission::where('code', 'extensions.approve')->sole()->id]);
        $component = Component::factory()->create();
        $issue = Issue::create(['project_id' => $component->project_id, 'title' => 'Private issue', 'status' => 'Open']);
        DB::table('project_assignments')->insert(['project_id' => $component->project_id, 'user_id' => $reviewer->id, 'assignment_role' => 'Reviewer', 'can_approve_extension' => true]);
        $this->actingAs($requester)->post('/extensions', ['component_id' => $component->id, 'reviewer_id' => $reviewer->id, 'requested_end_date' => '2026-11-01', 'reason' => 'Review this delay'])->assertRedirect();
        $extension = ExtensionRequest::sole();
        $this->actingAs($reviewer)->get('/extensions')->assertOk()->assertSee('Review this delay')->assertSee('Record decision');
        $this->get('/issues/'.$issue->id)->assertNotFound();
        $this->post('/extensions/'.$extension->id.'/decision', ['decision' => 'Approved', 'remarks' => 'Independent review complete'])->assertRedirect();
        $this->assertSame('Approved', $extension->fresh()->status);
    }

    public function test_builtin_director_role_cannot_be_renamed_to_bypass_project_scope(): void
    {
        $super = $this->user('Super Admin');
        $role = Role::where('name', 'Project Director')->sole();
        $this->actingAs($super)->put('/roles/'.$role->id, ['name' => 'Unrestricted director'])->assertSessionHasErrors('name');
        $this->assertSame('Project Director', $role->fresh()->name);
    }

    public function test_admin_email_edit_invalidates_previous_verification_links(): void
    {
        $admin = $this->user('Super Admin');
        $user = $this->user('Project Director');
        $url = '';
        $this->partialMock(EmailChangeMailer::class)->shouldReceive('send')->once()->andReturnUsing(function (User $recipient, string $email, string $link) use (&$url): void {
            $url = $link;
        });
        $this->actingAs($user)->put('/account', ['name' => $user->name, 'email' => 'pending@example.test', 'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->pending_email_token_hash);

        $this->actingAs($admin)->put('/users/'.$user->id, ['name' => $user->name, 'email' => 'corrected@example.test', 'role_id' => $user->role_id, 'account_status' => 'Active'])->assertRedirect();

        $this->assertSame('corrected@example.test', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->pending_email);
        $this->assertNull($user->fresh()->pending_email_token_hash);
        $this->actingAs($user->fresh())->post($url)->assertForbidden();
    }
}
