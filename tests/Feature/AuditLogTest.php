<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_logs_are_accessible_without_login_and_filterable(): void
    {
        $this->get(route('logs.index'))->assertOk()->assertSee('Demo activity history')->assertSee('District Water Supply Improvement');
        $this->get(route('logs.index', ['module' => 'accounts']))->assertOk()->assertSee('Sara Ahmed')->assertDontSee('District Water Supply Improvement');
        $this->get(route('logs.index', ['action' => 'deleted', 'search' => 'feasibility']))->assertOk()->assertSee('Missing feasibility document')->assertDontSee('Draft Coordination Meeting');
        $this->get(route('logs.index', ['search' => 'no-match-example']))->assertOk()->assertSee('No sample logs match');
    }
}
