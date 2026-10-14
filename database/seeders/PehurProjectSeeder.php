<?php

namespace Database\Seeders;

use App\Models\AnnualFinancial;
use App\Models\Component;
use App\Models\Contact;
use App\Models\District;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\FinancialReport;
use App\Models\FinancialYear;
use App\Models\FinancingAgreement;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectCostVersion;
use App\Models\ProjectScheduleRevision;
use App\Models\QuarterlyFinancial;
use App\Models\ReportingPeriod;
use App\Models\Sector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PehurProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['PKR' => 'Pakistan rupee', 'USD' => 'US dollar'] as $code => $name) {
                DB::table('currencies')->insertOrIgnore(['code' => $code, 'name' => $name, 'decimal_places' => 2]);
            }
            $this->call(PipelineTemplateSeeder::class);
            $sector = Sector::firstOrCreate(['code' => 'WATER'], ['name' => 'Water (irrigation)']);
            $district = District::firstOrCreate(['name' => 'Swabi'], ['code' => 'KP-SWABI', 'active' => true]);
            $nowshera = District::firstOrCreate(['name' => 'Nowshera'], ['code' => 'KP-NOWSHERA', 'active' => true]);
            $donor = Organization::firstOrCreate(['code' => 'ADB'], ['name' => 'Asian Development Bank (ADB)', 'organization_type' => 'Donor']);
            $agency = Organization::firstOrCreate(['code' => 'IRRIGATION'], ['name' => 'Irrigation Department', 'organization_type' => 'Government department']);
            $project = Project::firstOrCreate(['reference_code' => 'PHLCE-001'], [
                'name' => 'Pehur High Level Canal Extension Project', 'phase' => 'Ongoing', 'lifecycle' => 'Active',
                'sector_id' => $sector->id, 'district_id' => $district->id,
                'objective' => 'Develop a new irrigated area of over 8,727 hectares in Swabi and Nowshera by extending the Pehur High Level Canal irrigation system, improving water availability and irrigation efficiency, and increasing agricultural production.',
                'start_date' => '2017-06-09', 'original_end_date' => '2023-06-30', 'current_end_date' => '2027-06-30',
                'funding_type' => 'Loan — ADB OCR; market-based rate; 25 years including 5-year grace',
                'source_rating' => 'For Attention', 'reported_progress' => '85.80', 'progress_as_of' => '2026-09-09',
                'progress_scope' => 'Overall civil works only. Individual component progress was not reported.',
                'source_notes' => 'Source: Pehur High Level Canal Extension Project data collection document, based on the ADB 2nd Tripartite Portfolio Review (9 September 2026). Districts: Swabi and Nowshera. Approval forum/date and component dates/progress are unavailable. PC-I revision: CDWP held; ECNEC approval awaited, target 31 October 2026 tentative. Office field repeats a phone number and has not been imported as an address. Financial year record 1 says FY 2026-27 target USD 10 million, achieved USD 5.46 million; record 2/current-year summary says USD 6.9 million and USD 0.72 million. These conflicting records remain separate drafts. Quarterly plans total USD 7.14 million, exceeding the stated USD 6.9 million target. Do not combine draft revisions.',
            ]);
            foreach ([[$donor, 'donor'], [$agency, 'implementing'], [$agency, 'executing']] as [$organization, $role]) {
                DB::table('project_organizations')->insertOrIgnore(['project_id' => $project->id, 'organization_id' => $organization->id, 'role' => $role]);
            }
            $contact = Contact::firstOrCreate(['name' => 'Zar Gul', 'email' => 'Pmophlce@gmail.com'], ['organization_id' => $agency->id, 'designation' => 'Project Director', 'phone' => '0919210499']);
            DB::table('project_contacts')->insertOrIgnore(['project_id' => $project->id, 'contact_id' => $contact->id, 'responsibility' => 'Project Director', 'is_primary' => true]);
            $sourceDocument = null;
            $sourcePath = 'project-sources/pehur.docx';
            if (Storage::disk('local')->exists($sourcePath)) {
                $sourceDocument = Document::firstOrCreate(['path' => $sourcePath], ['title' => 'Pehur project data collection document', 'disk' => 'local', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'size' => Storage::disk('local')->size($sourcePath), 'sha256' => hash_file('sha256', Storage::disk('local')->path($sourcePath))]);
                DocumentLink::firstOrCreate(['document_id' => $sourceDocument->id, 'project_id' => $project->id]);
            }
            ProjectScheduleRevision::firstOrCreate(['project_id' => $project->id, 'revision_no' => 1], ['previous_end' => '2023-06-30', 'revised_end' => '2027-06-30', 'source_document_id' => $sourceDocument?->id, 'reason' => 'Revised closing date reported in the source. Approver and approval date were not supplied.']);
            ProjectCostVersion::firstOrCreate(['project_id' => $project->id, 'version_no' => 1], ['currency_code' => 'PKR', 'total_cost' => '15654000000.00']);
            $components = [];
            foreach ([
                ['LOT-1', 'Construction of Janda Boka Canal System through pressure pipe.'],
                ['LOT-2', 'Indus Ambar pressure pipe and connection from Gandaf tunnel. Contractor payment dispute; arbitration award received.'],
                ['LOT-3', 'Indus Ambar canal system and tributaries; command area development.'],
                ['Output-2 (Watercourses)', '119 watercourses at Janda Boka and Indus Ambar; 10 pending.'],
            ] as $index => [$name, $remarks]) {
                $components[$index + 1] = Component::firstOrCreate(['project_id' => $project->id, 'sequence_no' => $index + 1], ['name' => $name, 'lifecycle' => 'Ongoing', 'responsible_office' => 'Irrigation Department', 'remarks' => $remarks]);
            }
            $issues = [
                ['Civil works progress & procurement of steel sheets', 'Pending steel sheets, pipe installation and civil works. 729 tons received; fabrication in process; 60 more tons under procurement. 1.1 km pipe installation and civil works pending.', '2026-10-31', 'In Progress', null],
                ['PKR 171 million counterpart funding pending', 'CFY allocation PKR 100 million; PKR 40 million released. Additional funds required in FY 2026-27. These source figures differ in scope from the annual financial summary.', '2026-10-31', 'In Progress', null],
                ['Operationalization of SCADA system', 'SCADA installation depends on 100% completion of physical works.', '2026-12-31', 'Open', null],
                ['Contractor payment dispute (Lot 2)', 'Arbitration award received; contractor has not submitted IPA. GoKP liabilities PKR 184 million versus PKR 105.53 million allocation; source reports approximately PKR 79 million shortfall.', '2026-09-15', 'In Progress', 2],
                ['PC-1 revision', 'CDWP held; ECNEC approval awaited. Target date is tentative.', '2026-10-31', 'In Progress', null],
                ['Pending watercourses', '10 watercourses pending. Additional DD responsibilities assigned to existing PMO staff.', '2026-12-31', 'In Progress', 4],
            ];
            foreach ($issues as $index => [$title, $description, $due, $status, $component]) {
                Issue::firstOrCreate(['source_key' => 'PHLCE-001-ISSUE-'.($index + 1)], ['project_id' => $project->id, 'component_id' => $component ? $components[$component]->id : null, 'title' => $title, 'description' => $description, 'due_date' => $due, 'status' => $status]);
            }
            if ($project->wasRecentlyCreated || $project->district?->code === 'MULTI') {
                $project->update(['district_id' => $district->id, 'source_notes' => str_replace('District classification: Multi-district (Swabi and Nowshera).', 'Districts: Swabi and Nowshera.', $project->source_notes ?? '')]);
                $project->districts()->sync([$district->id, $nowshera->id]);
            } elseif (! $project->districts()->exists()) {
                $project->districts()->attach($project->district_id);
            }
            FinancingAgreement::firstOrCreate(['project_id' => $project->id, 'donor_id' => $donor->id, 'currency_code' => 'USD'], ['terms' => $project->funding_type]);
            $year = FinancialYear::firstOrCreate(['label' => '2026-27'], ['start_date' => '2026-07-01', 'end_date' => '2027-06-30']);
            $report = FinancialReport::firstOrCreate(['project_id' => $project->id, 'financial_year_id' => $year->id], ['revision_no' => 1, 'as_of_date' => '2026-09-09', 'currency_code' => 'USD', 'source_notes' => 'Latest source figures retained as the editable financial record. Quarterly plans total USD 7.14 million versus the USD 6.9 million target; review and reconcile these figures.']);
            AnnualFinancial::firstOrCreate(['report_id' => $report->id], ['currency_code' => $report->currency_code, 'disbursement_target' => '6900000.00', 'disbursement_ytd' => '720000.00']);
            foreach (['1200000.00', '4000000.00', '340000.00', '1600000.00'] as $index => $amount) {
                $start = $year->start_date->copy()->addMonths($index * 3);
                $period = ReportingPeriod::firstOrCreate(['financial_year_id' => $year->id, 'quarter_no' => $index + 1], ['start_date' => $start, 'end_date' => $start->copy()->addMonths(3)->subDay()]);
                QuarterlyFinancial::firstOrCreate(['report_id' => $report->id, 'period_id' => $period->id], ['currency_code' => $report->currency_code, 'planned_disbursement' => $amount]);
            }
        });
    }
}
