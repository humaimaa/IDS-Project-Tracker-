<?php

namespace Tests\Feature;

use App\Models\TrackerRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_header_and_sidebar_show_the_same_assigned_role(): void
    {
        $user = User::factory()->create();
        TrackerRecord::factory()->create(['module' => 'users', 'data' => ['email' => $user->email, 'role' => 'Supervisor']]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertDontSee('Section Officer</p>', false);
        $this->assertSame(2, substr_count($response->getContent(), 'Supervisor</p>'));
    }

    public function test_first_account_can_be_created_only_once(): void
    {
        $values = ['name' => 'Aina Khan', 'email' => 'aina@example.com', 'password' => 'first-password-123', 'password_confirmation' => 'first-password-123'];
        $this->get('/account/login')->assertSee('Set up your account');
        $this->post('/account/setup', $values)->assertRedirect(route('account.edit'))->assertSessionHasNoErrors();
        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check($values['password'], $user->password));
        $this->post('/logout');
        $this->post('/account/setup', array_replace($values, ['email' => 'another@example.com']))->assertForbidden();
        $this->assertDatabaseCount('users', 1);
        $this->get('/login')->assertSee('Log in')->assertDontSee('Set up your account');
    }

    public function test_guests_cannot_read_or_update_accounts(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
        $this->put('/account', ['name' => 'Changed'])->assertRedirect(route('login'));
    }

    public function test_user_can_change_own_name_and_email_without_changing_password(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $password = $user->password;

        $this->actingAs($user)->put('/account', [
            'name' => 'Aina Khan', 'email' => 'aina@example.com',
            'current_password' => 'password', 'id' => $other->id,
        ])->assertRedirect(route('account.edit'))->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Aina Khan', $user->name);
        $this->assertSame('aina@example.com', $user->email);
        $this->assertSame($password, $user->password);
        $this->assertNull($user->email_verified_at);
        $this->assertSame($other->email, $other->fresh()->email);
        $this->get('/account')->assertOk()->assertSee('Aina Khan')->assertSee('Logout')->assertSee('aina@example.com');
    }

    public function test_password_change_is_hashed_and_new_credentials_work(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/account', [
            'name' => $user->name, 'email' => $user->email,
            'current_password' => 'password', 'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'new-password-123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_account_changes_leave_the_account_unchanged(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->put('/account', [
            'name' => '', 'email' => $other->email, 'current_password' => 'incorrect',
            'password' => 'short', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['name', 'email', 'current_password', 'password']);

        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_email_change_requires_current_password_but_name_change_does_not(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/account', [
            'name' => 'New name', 'email' => 'changed@example.com',
        ])->assertSessionHasErrors('current_password');
        $this->assertSame($user->email, $user->fresh()->email);

        $this->put('/account', ['name' => 'New name', 'email' => $user->email])->assertSessionHasNoErrors();
        $this->assertSame('New name', $user->fresh()->name);
    }

    public function test_login_is_throttled_and_account_name_is_escaped(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertTooManyRequests();

        $this->actingAs($user)->get('/account')->assertSee($user->name)->assertDontSee($user->name, false);
    }
}
