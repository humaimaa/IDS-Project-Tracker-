<?php

namespace Tests\Feature;

use App\Models\FinancialReport;
use App\Models\FinancialYear;
use App\Models\PipelineStepTemplate;
use App\Models\Project;
use App\Models\ProjectPipelineStep;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PehurProjectSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineTemplateSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DuplicatePortfolioRecordsTest extends TestCase
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

    private function signIn(): void
    {
        $this->seed([PermissionSeeder::class, PehurProjectSeeder::class, PipelineTemplateSeeder::class]);
        $this->actingAs(User::factory()->create(['role_id' => Role::where('name', 'Super Admin')->sole()->id, 'account_status' => 'Active']));
    }

    public function test_duplicate_annual_and_quarterly_figures_return_validation_errors_and_preserve_amounts(): void
    {
        $this->signIn();
        $report = FinancialReport::sole();
        $annual = $report->annuals()->sole();
        $quarter = $report->quarters()->firstOrFail();
        $this->post('/annual-financials', ['report_id' => $report->id, 'currency_code' => $report->currency_code, 'disbursement_target' => 999])->assertSessionHasErrors('record');
        $this->post('/quarterly-financials', ['report_id' => $report->id, 'period_id' => $quarter->period_id, 'currency_code' => $report->currency_code, 'planned_disbursement' => 999])->assertSessionHasErrors('record');
        $this->assertSame($annual->disbursement_target, $annual->fresh()->disbursement_target);
        $this->assertSame($quarter->planned_disbursement, $quarter->fresh()->planned_disbursement);
        $this->assertSame(1, $report->annuals()->count());
        $this->assertSame(4, $report->quarters()->count());
    }

    public function test_existing_financial_year_is_rejected_by_both_entry_routes(): void
    {
        $this->signIn();
        $report = FinancialReport::firstOrFail();
        $data = ['project_id' => $report->project_id, 'financial_year_id' => $report->financial_year_id, 'currency_code' => 'PKR', 'as_of_date' => today()->toDateString()];
        $count = FinancialReport::count();
        $this->post('/financial-information', $data)->assertSessionHasErrors('financial_year_id');
        $this->post('/financial-information', ['project_id' => $report->project_id, 'currency_code' => 'PKR', 'years' => [['financial_year_id' => $report->financial_year_id, 'as_of_date' => today()->toDateString()]]])->assertSessionHasErrors('years.0.financial_year_id');
        $this->post('/financial-reports', $data + ['revision_no' => 99, 'status' => 'Draft'])->assertSessionHasErrors('financial_year_id');
        $this->assertDatabaseCount('financial_reports', $count);
    }

    public function test_repeated_submission_is_rejected_but_edit_and_another_project_are_allowed(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['phase' => 'Ongoing']);
        $data = ['project_id' => $project->id, 'financial_year_id' => FinancialYear::firstOrFail()->id, 'currency_code' => 'PKR', 'as_of_date' => today()->toDateString()];
        $this->post('/financial-information', $data)->assertSessionHasNoErrors();
        $report = $project->financialReports()->sole();
        $this->post('/financial-information', $data)->assertSessionHasErrors('financial_year_id');
        $this->put('/financial-information', $data + ['report_id' => $report->id, 'source_notes' => 'Updated'])->assertSessionHasNoErrors();
        $this->assertSame('Updated', $report->fresh()->source_notes);
        $this->assertSame(1, $project->financialReports()->count());
        $other = Project::factory()->create(['phase' => 'Ongoing']);
        $this->post('/financial-information', array_replace($data, ['project_id' => $other->id]))->assertSessionHasNoErrors();
        $this->assertSame(1, $other->financialReports()->count());
        $this->put(route('financial-reports.update', $report), $data + ['revision_no' => 12, 'status' => 'Draft'])->assertSessionHasErrors('financial_year_id');
        $this->assertSame(1, $report->fresh()->revision_no);
    }

    public function test_pipeline_duplicate_is_rejected_without_changing_existing_step_and_another_project_can_use_it(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['phase' => 'Pipeline']);
        $data = ['project_id' => $project->id, 'template_id' => PipelineStepTemplate::firstOrFail()->id, 'name' => 'Initial step', 'status' => 'Not started', 'is_current' => false];
        $this->post('/pipeline-steps', $data)->assertSessionHasNoErrors();
        $step = $project->pipelineSteps()->sole();
        $this->post('/pipeline-steps', array_replace($data, ['name' => 'Duplicate']))->assertSessionHasErrors('template_id');
        $this->put(route('pipeline-steps.update', $step), array_replace($data, ['name' => 'Updated step']))->assertSessionHasNoErrors();
        $this->assertSame(1, $project->pipelineSteps()->count());
        $this->assertSame('Updated step', $step->fresh()->name);
        $other = Project::factory()->create(['phase' => 'Pipeline']);
        $this->post('/pipeline-steps', array_replace($data, ['project_id' => $other->id]))->assertSessionHasNoErrors();
    }

    public function test_database_rejects_pipeline_duplicates_even_without_form_validation(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['phase' => 'Pipeline']);
        $template = PipelineStepTemplate::firstOrFail();
        $data = ['project_id' => $project->id, 'template_id' => $template->id, 'name' => $template->name, 'sort_order' => $template->sort_order];
        ProjectPipelineStep::create($data);
        $this->expectException(QueryException::class);
        ProjectPipelineStep::create($data);
    }

    public function test_database_rejects_a_second_report_for_the_same_project_and_year(): void
    {
        $this->signIn();
        $report = FinancialReport::firstOrFail();
        $this->expectException(QueryException::class);
        FinancialReport::create(['project_id' => $report->project_id, 'financial_year_id' => $report->financial_year_id, 'currency_code' => 'PKR', 'revision_no' => 99, 'status' => 'Draft', 'as_of_date' => today()]);
    }

    public function test_one_currency_is_editable_and_cumulative_donor_financing_is_removed(): void
    {
        $this->signIn();
        $report = FinancialReport::firstOrFail();
        $this->get(route('financial-information.edit', $report))->assertOk()->assertDontSee('Cumulative donor financing')->assertDontSee('Existing agreement');
        $this->put('/financial-information', ['report_id' => $report->id, 'project_id' => $report->project_id, 'financial_year_id' => $report->financial_year_id, 'currency_code' => 'PKR', 'as_of_date' => today()->toDateString(), 'annual' => ['disbursement_target' => 900], 'quarters' => [1 => ['planned_disbursement' => 225]]])->assertSessionHasNoErrors();
        $report->refresh();
        $this->assertSame('PKR', $report->currency_code);
        $this->assertCount(1, $report->annuals);
        $this->assertSame('900.00', $report->annuals->sole()->disbursement_target);
        $this->assertSame(['PKR'], $report->quarters->pluck('currency_code')->unique()->values()->all());
        $page = $this->get(route('projects.show', ['project' => $report->project_id, 'tab' => 'financial']))->assertOk()->assertSee('PKR million')->assertDontSee('Donor financing')->assertDontSee('USD million');
        $this->assertSame(1, substr_count($page->getContent(), 'class="quarter-chart"'));
        $this->assertFalse(Schema::hasTable('financial_totals'));
        $this->get('/financial-totals')->assertNotFound();
        $this->seed(PehurProjectSeeder::class);
        $this->assertSame('900.00', $report->fresh()->annuals->sole()->disbursement_target);
    }

    public function test_consolidation_keeps_latest_figures_and_preserves_other_project_data(): void
    {
        $this->signIn();
        $original = FinancialReport::firstOrFail();
        $documentId = DB::table('documents')->insertGetId(['title' => 'Original source', 'disk' => 'local', 'path' => 'original.pdf', 'mime_type' => 'application/pdf', 'size' => 100]);
        DB::table('document_links')->insert(['document_id' => $documentId, 'financial_report_id' => $original->id]);
        DB::statement('PRAGMA defer_foreign_keys = ON');
        Schema::table('financial_reports', function (Blueprint $table): void {
            $table->dropUnique('financial_project_year_unique');
            $table->dropForeign(['currency_code']);
            $table->dropColumn('currency_code');
            $table->string('status')->default('Draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
        });
        Schema::table('annual_financials', fn (Blueprint $table) => $table->dropUnique('financial_annual_report_unique'));
        Schema::table('quarterly_financials', fn (Blueprint $table) => $table->dropUnique('financial_quarter_report_period_unique'));
        $latestId = DB::table('financial_reports')->insertGetId(['project_id' => $original->project_id, 'financial_year_id' => $original->financial_year_id, 'revision_no' => 2, 'as_of_date' => '2026-09-09', 'status' => 'Draft']);
        DB::table('annual_financials')->insert(['report_id' => $latestId, 'currency_code' => 'USD', 'disbursement_target' => 500]);
        DB::table('annual_financials')->insert(['report_id' => $latestId, 'currency_code' => 'PKR', 'disbursement_target' => 1000]);
        $costCount = DB::table('project_cost_versions')->count();
        $migration = require database_path('migrations/2026_10_13_105022_simplify_project_financial_reports.php');
        $migration->up();
        $this->assertDatabaseCount('financial_reports', 1);
        $this->assertDatabaseCount('annual_financials', 1);
        $this->assertDatabaseHas('financial_reports', ['id' => $latestId, 'currency_code' => 'USD', 'status' => 'Draft', 'revision_no' => 1]);
        $this->assertDatabaseHas('annual_financials', ['report_id' => $latestId, 'disbursement_target' => 500]);
        $this->assertDatabaseCount('project_cost_versions', $costCount);
        $this->assertDatabaseHas('projects', ['id' => $original->project_id]);
        $this->assertDatabaseHas('document_links', ['document_id' => $documentId, 'financial_report_id' => $latestId]);
    }
}
