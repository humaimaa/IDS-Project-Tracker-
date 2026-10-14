<?php

use App\Models\AnnualFinancial;
use App\Models\Component;
use App\Models\Contact;
use App\Models\District;
use App\Models\FinancialReport;
use App\Models\FinancialYear;
use App\Models\FinancingAgreement;
use App\Models\Issue;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\PipelineStepTemplate;
use App\Models\ProgressUpdate;
use App\Models\ProjectCostVersion;
use App\Models\ProjectPipelineStep;
use App\Models\QuarterlyFinancial;
use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Subcomponent;
use App\Models\User;

return [
    'activities' => ['model' => Component::class, 'title' => 'Components', 'permission' => 'manage-projects', 'fields' => ['project_id' => 'required|exists:projects,id', 'name' => 'required|string|max:255', 'sequence_no' => 'required|integer|min:1', 'start_date' => 'nullable|date', 'baseline_end' => 'nullable|date|after_or_equal:start_date', 'lifecycle' => 'required|in:Not started,Ongoing,Completed,Cancelled', 'responsible_office' => 'nullable|string|max:255', 'remarks' => 'nullable|string|max:10000']],
    'subcomponents' => ['model' => Subcomponent::class, 'title' => 'Subcomponents', 'permission' => 'manage-projects', 'fields' => ['component_id' => 'required|exists:components,id', 'name' => 'required|string|max:255', 'sequence_no' => 'required|integer|min:1', 'start_date' => 'nullable|date', 'baseline_end' => 'nullable|date|after_or_equal:start_date', 'lifecycle' => 'required|in:Not started,Ongoing,Completed,Cancelled', 'responsible_office' => 'nullable|string|max:255', 'remarks' => 'nullable|string|max:10000']],
    'pipeline-steps' => ['model' => ProjectPipelineStep::class, 'title' => 'Pipeline steps', 'permission' => 'manage-projects', 'fields' => ['project_id' => 'required|exists:projects,id', 'template_id' => 'required|exists:pipeline_step_templates,id', 'name' => 'required|string|max:255', 'status' => 'required|in:Not started,In progress,Done', 'start_date' => 'nullable|date', 'responsible_office' => 'nullable|string|max:255', 'planned_date' => 'nullable|date|after_or_equal:start_date', 'completed_date' => 'nullable|date', 'is_current' => 'required|boolean', 'remarks' => 'nullable|string|max:10000']],
    'progress-updates' => ['model' => ProgressUpdate::class, 'title' => 'Progress updates', 'permission' => 'manage-projects', 'append_only' => true, 'fields' => ['component_id' => 'nullable|exists:components,id', 'subcomponent_id' => 'nullable|exists:subcomponents,id', 'as_of_date' => 'required|date|before_or_equal:today', 'progress_percent' => 'required|numeric|min:0|max:100', 'status' => 'required|in:Not started,Ongoing,Completed', 'remarks' => 'nullable|string|max:10000']],
    'issues' => ['model' => Issue::class, 'title' => 'Issues', 'permission' => 'manage-projects', 'fields' => ['project_id' => 'required|exists:projects,id', 'pipeline_step_id' => 'nullable|exists:project_pipeline_steps,id', 'component_id' => 'nullable|exists:components,id', 'subcomponent_id' => 'nullable|exists:subcomponents,id', 'title' => 'required|string|max:255', 'description' => 'nullable|string|max:10000', 'severity' => 'nullable|in:Low,Medium,High,Critical', 'status' => 'required|in:Open,In Progress,Resolved,Closed,Cancelled', 'due_date' => 'nullable|date', 'responsible_entity' => 'nullable|string|max:255', 'resolution' => 'nullable|string|max:10000']],
    'meetings' => ['model' => Meeting::class, 'title' => 'Meetings', 'permission' => 'manage-meetings', 'fields' => ['name' => 'required|string|max:255', 'scheduled_at' => 'required|date', 'location' => 'nullable|string|max:255', 'agenda' => 'nullable|string|max:20000', 'minutes' => 'nullable|string|max:40000', 'status' => 'required|in:Scheduled,Held,Cancelled']],
    'sectors' => ['model' => Sector::class, 'title' => 'Sectors', 'permission' => 'manage-reference-data', 'fields' => ['code' => 'required|string|max:50', 'name' => 'required|string|max:255', 'active' => 'required|boolean']],
    'districts' => ['model' => District::class, 'title' => 'Districts', 'permission' => 'manage-reference-data', 'fields' => ['code' => 'required|string|max:50', 'name' => 'required|string|max:255', 'active' => 'required|boolean']],
    'partners' => ['model' => Organization::class, 'title' => 'Development partners and organizations', 'permission' => 'manage-reference-data', 'fields' => ['code' => 'required|string|max:50', 'name' => 'required|string|max:255', 'organization_type' => 'required|in:Donor,Government department,Implementing agency,Other', 'active' => 'required|boolean']],
    'contacts' => ['model' => Contact::class, 'title' => 'Contacts', 'permission' => 'manage-projects', 'fields' => ['organization_id' => 'nullable|exists:organizations,id', 'name' => 'required|string|max:255', 'designation' => 'nullable|string|max:255', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:50']],
    'roles' => ['model' => Role::class, 'title' => 'Roles', 'permission' => 'manage-users', 'fields' => ['name' => 'required|string|max:255']],
    'users' => ['model' => User::class, 'title' => 'Users', 'permission' => 'manage-users', 'fields' => ['name' => 'required|string|max:255', 'email' => 'required|email|max:255', 'password' => 'nullable|string|min:12|max:255', 'role_id' => 'nullable|exists:roles,id', 'organization_id' => 'nullable|exists:organizations,id', 'account_status' => 'required|in:Active,Disabled']],
    'pipeline-templates' => ['model' => PipelineStepTemplate::class, 'title' => 'Pipeline steps', 'permission' => 'manage-reference-data', 'fields' => ['name' => 'required|string|max:255', 'sort_order' => 'required|integer|min:1|max:100', 'active' => 'required|boolean']],
    'financial-years' => ['model' => FinancialYear::class, 'title' => 'Financial years', 'permission' => 'manage-finances', 'fields' => ['label' => 'required|string|max:20', 'start_date' => 'required|date', 'end_date' => 'required|date|after:start_date']],
    'reporting-periods' => ['model' => ReportingPeriod::class, 'title' => 'Reporting quarters', 'permission' => 'manage-finances', 'fields' => ['financial_year_id' => 'required|exists:financial_years,id', 'quarter_no' => 'required|integer|min:1|max:4', 'start_date' => 'required|date', 'end_date' => 'required|date|after:start_date']],
    'project-costs' => ['model' => ProjectCostVersion::class, 'title' => 'Project cost revisions', 'permission' => 'manage-finances', 'append_only' => true, 'fields' => ['project_id' => 'required|exists:projects,id', 'version_no' => 'required|integer|min:1', 'currency_code' => 'required|exists:currencies,code', 'total_cost' => 'required|numeric|min:0|max:999999999999999.99', 'foreign_cost' => 'nullable|numeric|min:0|max:999999999999999.99', 'local_cost' => 'nullable|numeric|min:0|max:999999999999999.99', 'effective_on' => 'nullable|date']],
    'financing-agreements' => ['model' => FinancingAgreement::class, 'title' => 'Financing agreements', 'permission' => 'manage-finances', 'fields' => ['project_id' => 'required|exists:projects,id', 'donor_id' => 'required|exists:organizations,id', 'currency_code' => 'required|exists:currencies,code', 'agreement_ref' => 'nullable|string|max:255', 'original_commitment' => 'nullable|numeric|min:0|max:999999999999999.99', 'signing_date' => 'nullable|date', 'terms' => 'nullable|string|max:10000']],
    'financial-reports' => ['model' => FinancialReport::class, 'title' => 'Financial reports', 'permission' => 'manage-finances', 'fields' => ['project_id' => 'required|exists:projects,id', 'financial_year_id' => 'required|exists:financial_years,id', 'currency_code' => 'nullable|in:USD,PKR', 'as_of_date' => 'required|date', 'revision_no' => 'required|integer|min:1', 'source_notes' => 'nullable|string|max:10000']],
    'annual-financials' => ['model' => AnnualFinancial::class, 'title' => 'Annual financial figures', 'permission' => 'manage-finances', 'fields' => ['report_id' => 'required|exists:financial_reports,id', 'currency_code' => 'required|exists:currencies,code', 'total_allocation' => 'nullable|numeric|min:0|max:999999999999999.99', 'expenditure_ytd' => 'nullable|numeric|min:0|max:999999999999999.99', 'cumulative_expenditure' => 'nullable|numeric|min:0|max:999999999999999.99', 'disbursement_target' => 'nullable|numeric|min:0|max:999999999999999.99', 'disbursement_ytd' => 'nullable|numeric|min:0|max:999999999999999.99']],
    'quarterly-financials' => ['model' => QuarterlyFinancial::class, 'title' => 'Quarterly financial figures', 'permission' => 'manage-finances', 'fields' => ['report_id' => 'required|exists:financial_reports,id', 'period_id' => 'required|exists:reporting_periods,id', 'currency_code' => 'required|exists:currencies,code', 'planned_disbursement' => 'nullable|numeric|min:0|max:999999999999999.99', 'actual_disbursement' => 'nullable|numeric|min:0|max:999999999999999.99', 'expenditure_amount' => 'nullable|numeric|min:0|max:999999999999999.99']],
];
