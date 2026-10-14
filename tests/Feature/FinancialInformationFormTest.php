<?php

namespace Tests\Feature;

use App\Models\FinancialReport;
use App\Models\FinancialYear;
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

class FinancialInformationFormTest extends TestCase
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

    private function payload(): array
    {
        $this->seed([PermissionSeeder::class, PehurProjectSeeder::class]);
        $this->actingAs(User::factory()->create(['role_id' => Role::where('name', 'Super Admin')->sole()->id, 'account_status' => 'Active']));
        $project = Project::where('reference_code', 'PHLCE-001')->sole();

        return ['project_id' => $project->id, 'financial_year_id' => FinancialYear::create(['label' => '2028-29', 'start_date' => '2028-07-01', 'end_date' => '2029-06-30'])->id, 'currency_code' => 'USD', 'as_of_date' => '2026-10-10', 'cost' => ['total_cost' => 1000], 'annual' => ['disbursement_target' => 100], 'quarters' => [1 => ['planned_disbursement' => 25], 2 => ['planned_disbursement' => 25], 3 => ['planned_disbursement' => 25], 4 => ['planned_disbursement' => 25]]];
    }

    public function test_one_submission_saves_the_report_and_all_financial_sections(): void
    {
        $payload = $this->payload();
        $this->get(route('financial-information.create'))->assertOk()->assertSee('Save financial information');
        $this->post(route('financial-information.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $report = FinancialReport::latest('id')->firstOrFail();
        $this->assertArrayNotHasKey('status', $report->getAttributes());
        $this->assertSame(1, $report->revision_no);
        $this->assertCount(4, $report->quarters);
        $this->assertSame('100.00', $report->annuals->sole()->disbursement_target);
        $this->assertDatabaseHas('project_cost_versions', ['project_id' => $payload['project_id'], 'version_no' => 2, 'total_cost' => 1000]);
    }

    public function test_invalid_late_section_rolls_back_all_financial_records(): void
    {
        $payload = $this->payload();
        $payload['quarters'][4]['planned_disbursement'] = -1;
        $this->post(route('financial-information.store'), $payload)->assertSessionHasErrors('planned_disbursement');
        $this->assertDatabaseCount('financial_reports', 1);
        $this->assertDatabaseCount('project_cost_versions', 1);
        $this->assertDatabaseCount('quarterly_financials', 4);
    }

    public function test_hidden_section_permission_is_enforced_when_payload_is_forged(): void
    {
        $payload = $this->payload();
        $role = Role::create(['name' => 'Report author only']);
        DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission_id' => Permission::where('code', 'financial-reports.create')->sole()->id]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'account_status' => 'Active']));
        $this->get(route('financial-information.create'))->assertOk()->assertDontSee('name="cost[total_cost]"', false)->assertDontSee('name="quarters[1][planned_disbursement]"', false);
        $this->post(route('financial-information.store'), $payload)->assertForbidden();
        $this->assertDatabaseCount('financial_reports', 1);
        $this->assertDatabaseCount('project_cost_versions', 1);
    }

    public function test_invalid_currency_is_rejected(): void
    {
        $payload = $this->payload();
        $payload['currency_code'] = 'INVALID';
        $this->post(route('financial-information.store'), $payload)->assertSessionHasErrors('currency_code');
        $this->assertDatabaseCount('financial_reports', 1);
    }

    public function test_multiple_years_save_together_and_existing_drafts_can_be_edited(): void
    {
        $payload = $this->payload();
        $nextYear = FinancialYear::create(['label' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']);
        $year = collect($payload)->only(['financial_year_id', 'as_of_date', 'annual', 'quarters'])->all();
        $shared = collect($payload)->only(['project_id', 'currency_code', 'cost'])->all();
        $shared['years'] = [$year, array_replace($year, ['financial_year_id' => $nextYear->id, 'as_of_date' => '2027-10-01'])];
        $this->post(route('financial-information.store'), $shared)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('financial_reports', 3);
        $this->assertDatabaseCount('project_cost_versions', 2);
        $report = FinancialReport::where('financial_year_id', $nextYear->id)->sole();
        $this->get(route('financial-information.edit', $report))->assertOk()->assertSee('Add another financial year')->assertSee('Edit financial information');
        $shared['years'] = [array_replace($shared['years'][1], ['report_id' => $report->id, 'annual' => ['disbursement_target' => 120]])];
        unset($shared['cost']);
        $this->put(route('financial-information.update'), $shared)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('financial_reports', 3);
        $this->assertSame('120.00', $report->fresh()->annuals->sole()->disbursement_target);
        $this->assertCount(4, $report->fresh()->quarters);
    }

    public function test_multi_year_failure_rolls_back_earlier_year_and_duplicate_years_are_rejected(): void
    {
        $payload = $this->payload();
        $year = collect($payload)->only(['financial_year_id', 'as_of_date', 'annual'])->all();
        $shared = collect($payload)->only(['project_id', 'currency_code'])->all();
        $this->post(route('financial-information.store'), $shared + ['years' => [$year, $year]])->assertSessionHasErrors('years.0.financial_year_id');
        $nextYear = FinancialYear::create(['label' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']);
        $invalid = array_replace($year, ['financial_year_id' => $nextYear->id, 'annual' => ['total_allocation' => -1]]);
        $this->post(route('financial-information.store'), $shared + ['years' => [$year, $invalid]])->assertSessionHasErrors('years.1.total_allocation');
        $this->assertDatabaseCount('financial_reports', 1);
    }

    public function test_edit_is_available_but_cannot_move_a_report_to_another_project(): void
    {
        $payload = $this->payload();
        $report = FinancialReport::firstOrFail();
        $this->get(route('financial-information.edit', $report))->assertOk();
        $this->put(route('financial-information.update'), $payload + ['report_id' => $report->id])->assertSessionHasErrors('report_id');
        $draft = FinancialReport::create(['project_id' => $report->project_id, 'financial_year_id' => $payload['financial_year_id'], 'currency_code' => 'USD', 'as_of_date' => today(), 'revision_no' => 1, 'status' => 'Draft']);
        $other = Project::factory()->create();
        $payload['project_id'] = $other->id;
        $this->put(route('financial-information.update'), $payload + ['report_id' => $draft->id])->assertSessionHasErrors('report_id');
        $this->assertDatabaseCount('financial_reports', 2);
        $this->assertArrayNotHasKey('status', $report->fresh()->getAttributes());
        $this->assertNotSame($other->id, $draft->fresh()->project_id);
    }
}
