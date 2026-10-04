<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertViewIs('landing')->assertSee('Every project. Every milestone. One place.')
            ->assertSee('href="'.route('login').'"', false)->assertSee('Our development partners');
    }

    public function test_demo_login_opens_the_dashboard_without_authenticating_a_user(): void
    {
        $this->get(route('login'))->assertOk()->assertViewIs('users.demo-login')
            ->assertSee('action="'.route('dashboard').'"', false)->assertSee('You can leave both fields empty.');
        $this->get(route('dashboard'))->assertOk()->assertViewIs('dashboard');
        $this->assertGuest();
        $this->post(route('login.store'), [])->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }
}
