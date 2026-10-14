<?php

namespace Tests\Feature;

use App\Models\FinancialReport;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PehurProjectSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FinancialInformationListTest extends TestCase
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

    private function signIn(): Project
    {
        $this->seed([PermissionSeeder::class, PehurProjectSeeder::class]);
        $this->actingAs(User::factory()->create(['role_id' => Role::where('name', 'Super Admin')->sole()->id, 'account_status' => 'Active']));

        return Project::where('reference_code', 'PHLCE-001')->sole();
    }

    public function test_financial_list_shows_project_name_latest_cost_year_and_edit_link(): void
    {
        $project = $this->signIn();
        $project->costVersions()->create(['version_no' => 2, 'currency_code' => 'PKR', 'total_cost' => 1234567]);
        $report = $project->financialReports->first();
        $this->get('/financial-reports')->assertOk()->assertSee($project->name)->assertSee('PKR 1,234,567.00')->assertSee($report->financialYear->label)->assertSee('href="'.route('financial-information.edit', $report).'"', false);
        $this->get('/financial-reports')->assertOk()->assertDontSee('<th>Report status</th>', false);
    }

    public function test_archiving_a_project_archives_its_financial_information_and_restoring_reverses_it(): void
    {
        $project = $this->signIn();
        $report = $project->financialReports->first();
        $this->post(route('projects.archive', $project), ['archived' => 1])->assertRedirect();
        $this->get('/financial-reports')->assertOk()->assertViewHas('records', fn ($records): bool => $records->isEmpty());
        $this->get('/financial-reports?archived=1')->assertOk()->assertSee($project->name)->assertDontSee('href="'.route('financial-information.edit', $report).'"', false);
        $this->get(route('financial-information.edit', $report))->assertForbidden();
        $this->get(route('financial-reports.edit', $report))->assertForbidden();
        $this->get(route('projects.show', ['project' => $project, 'tab' => 'financial']))->assertOk()->assertDontSee('Edit draft report')->assertDontSee('Approve report')->assertDontSee('+ Add agreement');
        $this->put(route('financial-reports.update', $report), ['project_id' => $project->id, 'financial_year_id' => $report->financial_year_id, 'as_of_date' => $report->as_of_date->toDateString(), 'revision_no' => $report->revision_no, 'status' => 'Draft'])->assertSessionHasErrors('project_id');
        $this->assertDatabaseHas('financial_reports', ['id' => $report->id, 'project_id' => $project->id]);
        $this->post(route('projects.archive', $project), ['archived' => 0])->assertRedirect();
        $this->get('/financial-reports')->assertOk()->assertSee($project->name)->assertSee('href="'.route('financial-information.edit', $report).'"', false);
        $this->get('/financial-reports?archived=1')->assertViewHas('records', fn ($records): bool => $records->isEmpty());
    }

    public function test_costs_and_edit_buttons_respect_permissions(): void
    {
        $project = $this->signIn();
        $role = Role::create(['name' => 'Financial reader']);
        DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission_id' => Permission::where('code', 'financial-reports.view')->sole()->id]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'account_status' => 'Active']));
        $this->get('/financial-reports')->assertOk()->assertSee($project->name)->assertDontSee('<th>Project cost</th>', false)->assertDontSee('href="'.route('financial-information.edit', FinancialReport::firstOrFail()).'"', false);
    }
}
