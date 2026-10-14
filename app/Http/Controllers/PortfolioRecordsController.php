<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Component;
use App\Models\FinancialReport;
use App\Models\FinancialYear;
use App\Models\IssueUpdate;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\PipelineStepTemplate;
use App\Models\Project;
use App\Models\ProjectPipelineStep;
use App\Models\ReportingPeriod;
use App\Models\Subcomponent;
use App\Models\User;
use App\PortfolioAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioRecordsController extends Controller
{
    public function downloadMinutes(Request $request, string $id): StreamedResponse
    {
        $meeting = PortfolioAccess::scope(Meeting::query())->findOrFail($id);
        abort_unless($meeting->minutes_path && Storage::disk('local')->exists($meeting->minutes_path), 404);

        return Storage::disk('local')->download($meeting->minutes_path, $meeting->minutes_filename);
    }

    public function financialForm(Request $request, ?string $id = null): View
    {
        $editingReport = $id ? PortfolioAccess::scope(FinancialReport::query())->with(['annuals', 'quarters.period'])->findOrFail($id) : null;
        abort_if($editingReport && $editingReport->project->archived_at, 403);

        return view('projects.financial-form', [
            'editingReport' => $editingReport,
            'projects' => PortfolioAccess::scope(Project::query())->where('phase', 'Ongoing')->whereNull('archived_at')->with('financialReports:id,project_id,financial_year_id')->orderBy('name')->get(),
            'years' => FinancialYear::orderByDesc('start_date')->get(),
            'currencies' => DB::table('currencies')->whereIn('code', ['USD', 'PKR'])->orderBy('code')->get(),
            'donors' => Organization::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeFinancialForm(Request $request): RedirectResponse
    {
        if (! $request->has('years')) {
            return $this->storeSingleFinancialYear($request);
        }
        $request->validate(['years' => 'required|array|min:1|max:20', 'years.*' => 'array:financial_year_id,report_id,as_of_date,source_notes,annual,quarters', 'years.*.financial_year_id' => 'required|integer|distinct|exists:financial_years,id', 'years.*.report_id' => 'nullable|integer|distinct', 'project_id' => 'required|integer|exists:projects,id', 'currency_code' => 'required|in:USD,PKR']);

        return DB::transaction(function () use ($request): RedirectResponse {
            $shared = $request->only(['project_id', 'currency_code', 'cost', 'agreement']);
            $project = PortfolioAccess::scope(Project::query())->lockForUpdate()->findOrFail($shared['project_id']);
            abort_unless($project->phase === 'Ongoing' && ! $project->archived_at, 422);
            if ($this->hasFinancialValues($shared['agreement'] ?? [])) {
                $this->saveFinancialSection($request, 'financing-agreements', $shared['agreement'] + ['project_id' => $project->id, 'currency_code' => $shared['currency_code']]);
                unset($shared['agreement']);
            }
            foreach ($request->input('years') as $index => $year) {
                try {
                    $response = $this->storeSingleFinancialYear($request->duplicate([], $shared + $year));
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field): array => ['years.'.$index.'.'.$field => $messages])->all());
                }
                unset($shared['cost']);
            }

            return $response;
        });
    }

    private function storeSingleFinancialYear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'report_id' => 'nullable|integer|exists:financial_reports,id',
            'project_id' => 'required|integer|exists:projects,id',
            'financial_year_id' => 'required|integer|exists:financial_years,id',
            'currency_code' => 'required|in:USD,PKR',
            'as_of_date' => 'required|date',
            'source_notes' => 'nullable|string|max:10000',
            'cost' => 'sometimes|array:total_cost,foreign_cost,local_cost,effective_on',
            'agreement' => 'sometimes|array:donor_id,agreement_ref,original_commitment,signing_date,terms',
            'annual' => 'sometimes|array:total_allocation,expenditure_ytd,cumulative_expenditure,disbursement_target,disbursement_ytd',
            'quarters' => 'sometimes|array:1,2,3,4',
            'quarters.*' => 'array:planned_disbursement,actual_disbursement,expenditure_amount',
        ]);
        $project = PortfolioAccess::scope(Project::query())->findOrFail($data['project_id']);
        abort_unless($project->phase === 'Ongoing' && ! $project->archived_at, 422);
        $report = DB::transaction(function () use ($request, $data, $project): FinancialReport {
            $project->newQuery()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $currency = $data['currency_code'];
            $existing = ! empty($data['report_id']) ? PortfolioAccess::scope(FinancialReport::query())->lockForUpdate()->findOrFail($data['report_id']) : null;
            if ($existing && ($existing->project_id !== $project->id || $existing->financial_year_id !== (int) $data['financial_year_id'])) {
                throw ValidationException::withMessages(['report_id' => 'The report must belong to this project and financial year.']);
            }
            $report = $this->saveFinancialSection($request, 'financial-reports', [
                'project_id' => $project->id, 'financial_year_id' => $data['financial_year_id'], 'as_of_date' => $data['as_of_date'],
                'currency_code' => $currency,
                'revision_no' => $existing?->revision_no ?? (int) $project->financialReports()->where('financial_year_id', $data['financial_year_id'])->max('revision_no') + 1,
                'source_notes' => $data['source_notes'] ?? null,
            ], $existing);
            if ($this->hasFinancialValues($data['cost'] ?? [])) {
                $this->saveFinancialSection($request, 'project-costs', ($data['cost'] ?? []) + ['project_id' => $project->id, 'currency_code' => $currency, 'version_no' => (int) $project->costVersions()->max('version_no') + 1]);
            }
            if ($this->hasFinancialValues($data['agreement'] ?? [])) {
                $this->saveFinancialSection($request, 'financing-agreements', $data['agreement'] + ['project_id' => $project->id, 'currency_code' => $currency]);
            }
            if ($this->hasFinancialValues($data['annual'] ?? []) || ($existing && isset($data['annual']) && $report->annuals()->where('currency_code', $currency)->exists())) {
                $this->saveFinancialSection($request, 'annual-financials', $data['annual'] + ['report_id' => $report->id, 'currency_code' => $currency], $existing ? $report->annuals()->where('currency_code', $currency)->first() : null);
            }
            foreach ($data['quarters'] ?? [] as $quarter => $values) {
                if (! $this->hasFinancialValues($values) && ! ($existing && $report->quarters()->where('currency_code', $currency)->whereHas('period', fn ($query) => $query->where('quarter_no', $quarter))->exists())) {
                    continue;
                }
                $period = ReportingPeriod::where('financial_year_id', $data['financial_year_id'])->where('quarter_no', $quarter)->first();
                if (! $period) {
                    $year = FinancialYear::findOrFail($data['financial_year_id']);
                    $start = $year->start_date->copy()->addMonths(((int) $quarter - 1) * 3);
                    $period = $this->saveFinancialSection($request, 'reporting-periods', ['financial_year_id' => $year->id, 'quarter_no' => $quarter, 'start_date' => $start->toDateString(), 'end_date' => $start->copy()->addMonths(3)->subDay()->toDateString()]);
                }
                $this->saveFinancialSection($request, 'quarterly-financials', $values + ['report_id' => $report->id, 'period_id' => $period->id, 'currency_code' => $currency], $existing ? $report->quarters()->where('period_id', $period->id)->where('currency_code', $currency)->first() : null);
            }

            return $report;
        });

        return $request->user()->hasPortfolioPermission('projects.view') && $request->user()->hasPortfolioPermission('financial-reports.view')
            ? redirect()->route('projects.show', ['project' => $project, 'tab' => 'financial', 'year' => $report->financial_year_id, 'report' => $report->id])->with('success', 'Financial information saved.')
            : PortfolioAccess::redirectToAllowed('financial-information.edit', ['id' => $report->id])->with('success', 'Financial information saved.');
    }

    private function hasFinancialValues(array $values): bool
    {
        return collect($values)->contains(fn ($value): bool => $value !== null && $value !== '');
    }

    private function saveFinancialSection(Request $request, string $module, array $values, ?Model $existing = null): Model
    {
        abort_unless($request->user()->hasPortfolioPermission($module.($existing ? '.update' : '.create')), 403);
        $definition = config('portfolio_records.'.$module);
        $record = $existing ?? new $definition['model'];
        $sectionRequest = $request->duplicate([], $values);
        $route = clone $request->route();
        $route->setParameter('module', $module);
        $sectionRequest->setRouteResolver(fn () => $route);
        $this->save($sectionRequest, $definition, $record);

        return $record;
    }

    private function definition(Request $request, bool $write = false): array
    {
        $definition = config('portfolio_records.'.$request->route('module'));
        abort_unless($definition, 404);
        abort_unless($request->user()?->hasPortfolioPermission(PortfolioAccess::routePermission($request->route()->getName())), 403);

        return $definition;
    }

    public function index(Request $request): View
    {
        $definition = $this->definition($request);
        $query = PortfolioAccess::scope($definition['model']::query());
        $request->validate(['page' => 'nullable|integer|min:1', 'archived' => 'nullable|boolean']);
        if (isset($definition['fields']['active'])) {
            $query->where('active', ! $request->boolean('archived'));
        }
        if ($request->filled('project_id') && in_array('project_id', (new $definition['model'])->getFillable())) {
            $request->validate(['project_id' => 'integer']);
            $query->where('project_id', $request->integer('project_id'));
        } elseif ($request->filled('project_id') && in_array('report_id', (new $definition['model'])->getFillable())) {
            $request->validate(['project_id' => 'integer']);
            $query->whereIn('report_id', FinancialReport::where('project_id', $request->integer('project_id'))->select('id'));
        }

        if (in_array($request->route('module'), ['activities', 'pipeline-steps'])) {
            $phase = $request->route('module') === 'pipeline-steps' ? 'Pipeline' : 'Ongoing';
            $query->whereHas('project', fn ($project) => $project->where('phase', $phase))->with('project');
        }

        if (in_array($request->route('module'), ['financial-reports', 'financing-agreements', 'project-costs', 'annual-financials', 'quarterly-financials'])) {
            $projects = Project::query()->when($request->boolean('archived'), fn ($project) => $project->whereNotNull('archived_at'), fn ($project) => $project->whereNull('archived_at'))->select('id');
            if (in_array('project_id', (new $definition['model'])->getFillable())) {
                $query->whereIn('project_id', $projects);
            } else {
                $query->whereIn('report_id', FinancialReport::whereIn('project_id', $projects)->select('id'));
            }
        }
        if ($request->route('module') === 'financial-reports') {
            $query->with(['project', 'financialYear']);
            if ($request->user()->hasPortfolioPermission('project-costs.view')) {
                $query->with('project.costVersions');
            }
        }

        return view('projects.database-records', ['module' => $request->route('module'), 'definition' => $definition, 'records' => $query->latest('id')->paginate(25)->withQueryString()]);
    }

    public function show(Request $request, string $id): View
    {
        $definition = $this->definition($request);
        $record = PortfolioAccess::scope($definition['model']::query())->findOrFail($id);
        if ($record instanceof Meeting) {
            if ($request->user()) {
                DB::table('meeting_reads')->updateOrInsert(['meeting_id' => $record->id, 'user_id' => $request->user()->id], ['version' => $record->version, 'read_at' => now()]);
            } else {
                $request->session()->put('portfolio_meeting_reads.'.$record->id, $record->version);
            }
            $record->load(['projects' => fn ($q) => $request->user()->isProjectDirector() ? $q->whereIn('projects.id', $request->user()->ownedProjects()->select('projects.id')) : $q]);
        }

        return view('projects.database-record', ['module' => $request->route('module'), 'definition' => $definition, 'record' => $record, 'options' => $this->options($definition)]);
    }

    public function create(Request $request): View
    {
        $definition = $this->definition($request, true);
        abort_if($definition['existing_only'] ?? false, 404);
        if ($request->route('module') === 'financial-reports') {
            return $this->financialForm($request);
        }

        return $this->form($request, $definition, new $definition['model']);
    }

    public function edit(Request $request, string $id): View
    {
        $definition = $this->definition($request, true);
        abort_if($definition['append_only'] ?? false, 403);
        if ($request->route('module') === 'financial-reports') {
            return $this->financialForm($request, $id);
        }

        return $this->form($request, $definition, PortfolioAccess::scope($definition['model']::query())->findOrFail($id));
    }

    private function form(Request $request, array $definition, Model $record): View
    {
        abort_if($record instanceof FinancialReport && $record->exists && $record->project->archived_at, 403);
        if ($record instanceof User && $record->exists && $record->role_id) {
            abort_unless($request->user()->canGrantRole($record->role_id), 403);
        }

        if ($record->getTable() === 'roles' && $record->exists) {
            abort_unless($request->user()->canGrantRole($record->id) && $request->user()->role_id !== $record->id, 403);
        }

        return view('projects.database-record-form', ['module' => $request->route('module'), 'definition' => $definition, 'record' => $record, 'issueProjects' => $request->route('module') === 'issues' ? $this->issueProjects() : [], 'options' => $this->options($definition), 'projects' => PortfolioAccess::scope(Project::query())->orderBy('name')->get(), 'permissions' => DB::table('permissions')->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->whereIn('id', DB::table('role_permissions')->where('role_id', $request->user()->role_id)->select('permission_id')))->get(), 'selectedPermissions' => $record->getTable() === 'roles' ? DB::table('role_permissions')->where('role_id', $record->id)->pluck('permission_id')->all() : []]);
    }

    private function issueProjects(): array
    {
        return PortfolioAccess::scope(Project::query())->with(['components.subcomponents', 'pipelineSteps'])->orderBy('name')->get()->map(fn (Project $project): array => [
            'id' => $project->id, 'name' => $project->name, 'phase' => $project->phase,
            'components' => ($project->phase === 'Pipeline' ? $project->pipelineSteps : $project->components)->map(fn ($component): array => [
                'id' => $component->id, 'name' => $component->name,
                'subcomponents' => $project->phase === 'Pipeline' ? [] : $component->subcomponents->map(fn ($subcomponent): array => ['id' => $subcomponent->id, 'name' => $subcomponent->name])->all(),
            ])->all(),
        ])->all();
    }

    private function options(array $definition): array
    {
        $options = [];
        $labels = ['financial_years' => 'label', 'reporting_periods' => 'quarter_no', 'currencies' => 'name', 'financial_reports' => 'revision_no', 'financing_agreements' => 'agreement_ref'];
        foreach ($definition['fields'] as $field => $rule) {
            if (preg_match('/exists:([a-z_]+),([a-z_]+)/', $rule, $match)) {
                $table = $match[1];
                $label = $labels[$table] ?? 'name';
                $options[$field] = $this->optionRows($table)->mapWithKeys(function ($row) use ($table, $label, $match): array {
                    $name = $row->$label ?? ('Record '.$row->{$match[2]});
                    if ($table === 'financial_reports') {
                        $name = 'Project '.$row->project_id.' / year '.$row->financial_year_id.' / revision '.$row->revision_no;
                    } elseif ($table === 'reporting_periods') {
                        $name = 'Q'.$row->quarter_no.' ('.$row->start_date.' - '.$row->end_date.')';
                    } elseif ($table === 'financing_agreements') {
                        $name = 'Project '.$row->project_id.' / '.$row->currency_code.' / '.($row->agreement_ref ?? 'Agreement '.$row->id);
                    }

                    return [$row->{$match[2]} => $name];
                })->all();
            } elseif (preg_match('/(?:^|\|)in:([^|]+)/', $rule, $match)) {
                $options[$field] = array_combine(explode(',', $match[1]), explode(',', $match[1]));
            } elseif (str_contains($rule, 'boolean')) {
                $options[$field] = [0 => 'No', 1 => 'Yes'];
            }
        }

        return $options;
    }

    private function optionRows(string $table): Collection
    {
        $models = array_column(config('portfolio_records'), 'model');
        $models[] = Project::class;
        foreach ($models as $model) {
            if ((new $model)->getTable() === $table) {
                $query = PortfolioAccess::scope($model::query());
                if ($table === 'projects' && in_array(request()->route('module'), ['activities', 'pipeline-steps'])) {
                    $query->where('phase', request()->route('module') === 'activities' ? 'Ongoing' : 'Pipeline');
                }
                $rows = $query->get();

                return $table === 'roles' ? $rows->filter(fn ($role): bool => auth()->user()->canGrantRole($role->id)) : $rows;
            }
        }

        return DB::table($table)->get();
    }

    public function store(Request $request): RedirectResponse
    {
        $definition = $this->definition($request, true);
        abort_if($definition['existing_only'] ?? false, 403);

        return $this->save($request, $definition, new $definition['model']);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $definition = $this->definition($request, true);
        abort_if($definition['append_only'] ?? false, 403);

        return $this->save($request, $definition, PortfolioAccess::scope($definition['model']::query())->findOrFail($id));
    }

    private function save(Request $request, array $definition, Model $record): RedirectResponse
    {
        if ($record instanceof ProjectPipelineStep && $record->exists) {
            $request->mergeIfMissing($record->only(['project_id', 'template_id', 'name']));
        }
        $rules = $definition['fields'];
        foreach (['code', 'email'] as $unique) {
            if (isset($rules[$unique])) {
                $rules[$unique] = array_merge(explode('|', $rules[$unique]), [Rule::unique($record->getTable(), $unique)->ignore($record->id)]);
            }
        }
        if ($record instanceof User && ! $record->exists) {
            $rules['password'] = 'required|string|min:12|max:255';
        }
        if ($record instanceof Meeting) {
            $rules['projects'] = 'array';
            $rules['projects.*'] = 'integer|exists:projects,id';
            $rules['minutes_document'] = 'nullable|file|mimes:pdf,doc,docx|extensions:pdf,doc,docx|max:20480';
        }
        if ($record->getTable() === 'roles') {
            $rules['permissions'] = 'array';
            $rules['permissions.*'] = 'integer|exists:permissions,id';
        }
        $data = $request->validate($rules);
        $uploadedMinutesPath = null;
        try {
            DB::transaction(function () use ($request, $data, $record, &$uploadedMinutesPath): void {
                if ($record->exists) {
                    $record->setRawAttributes($record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail()->getAttributes(), true);
                }
                $data = $this->validateDomain($request, $record, $data);
                if ($record instanceof Meeting && $request->hasFile('minutes_document')) {
                    if ($data['status'] !== 'Held' || now()->lt($data['scheduled_at'])) {
                        throw ValidationException::withMessages(['minutes_document' => 'Minutes can only be uploaded for a meeting that has been held.']);
                    }
                    $uploadedMinutesPath = $request->file('minutes_document')->store('meeting-minutes', 'local');
                    $data['minutes_path'] = $uploadedMinutesPath;
                    $data['minutes_filename'] = $request->file('minutes_document')->getClientOriginalName();
                }
                if ($record instanceof User && empty($data['password'])) {
                    unset($data['password']);
                }
                $record->forceFill(collect($data)->except(['projects', 'permissions', 'minutes_document'])->all())->save();
                if ($record instanceof Meeting) {
                    $before = $record->projects()->pluck('projects.id')->all();
                    $record->projects()->sync($data['projects'] ?? []);
                    $this->logRelations($record, 'Related projects', $before, $data['projects'] ?? []);
                }
                if ($record->getTable() === 'roles') {
                    $before = DB::table('role_permissions')->where('role_id', $record->id)->pluck('permission_id')->all();
                    DB::table('role_permissions')->where('role_id', $record->id)->delete();
                    foreach (array_unique($data['permissions'] ?? []) as $permission) {
                        DB::table('role_permissions')->insert(['role_id' => $record->id, 'permission_id' => $permission]);
                    }
                    $this->logRelations($record, 'Permissions', $before, $data['permissions'] ?? []);
                }
                if ($record->getTable() === 'issues') {
                    IssueUpdate::create(['issue_id' => $record->id, 'actor_id' => $request->user()->id, 'status' => $record->status, 'remarks' => $record->resolution ?? $record->description ?? 'Issue updated.']);
                }
                if ($record->getTable() === 'progress_updates') {
                    $target = $record->component_id ? Component::findOrFail($record->component_id) : Subcomponent::findOrFail($record->subcomponent_id);
                    $latest = $record->newQuery()->where($record->component_id ? 'component_id' : 'subcomponent_id', $target->id)->orderByDesc('as_of_date')->orderByDesc('id')->first();
                    $target->update(['reported_progress' => $latest->progress_percent, 'lifecycle' => $latest->status, 'version' => $target->version + 1]);
                }
            });
        } catch (\Throwable $exception) {
            if ($uploadedMinutesPath) {
                Storage::disk('local')->delete($uploadedMinutesPath);
            }
            if ($exception instanceof QueryException && in_array((string) $exception->getCode(), ['23000', '23505', '23503'])) {
                throw ValidationException::withMessages(['record' => 'A record with this code or sequence already exists, or a related record is invalid.']);
            }
            throw $exception;
        }

        return PortfolioAccess::redirectToAllowed($request->route('module').'.show', ['id' => $record->id])->with('success', 'Record saved.');
    }

    private function validateDomain(Request $request, Model $record, array $data): array
    {
        $table = $record->getTable();
        if ($record instanceof FinancialReport) {
            $data['currency_code'] = $data['currency_code'] ?? $record->currency_code ?? 'PKR';
            if ($record->exists && $record->currency_code !== $data['currency_code']) {
                foreach (['annuals' => 'annual-financials.update', 'quarters' => 'quarterly-financials.update'] as $relation => $permission) {
                    if ($record->$relation()->exists()) {
                        abort_unless($request->user()->hasPortfolioPermission($permission), 403);
                    }
                    foreach ($record->$relation()->get() as $figures) {
                        $figures->update(['currency_code' => $data['currency_code']]);
                    }
                }
            }
            Project::whereKey($data['project_id'])->lockForUpdate()->firstOrFail();
            if (! $record->exists && FinancialReport::where('project_id', $data['project_id'])->where('financial_year_id', $data['financial_year_id'])->exists()) {
                throw ValidationException::withMessages(['financial_year_id' => 'Financial information already exists for this project and financial year. Edit the existing record instead.']);
            }
            if ($record->exists && ((int) $data['project_id'] !== $record->project_id || (int) $data['financial_year_id'] !== $record->financial_year_id || (int) $data['revision_no'] !== $record->revision_no)) {
                throw ValidationException::withMessages(['financial_year_id' => 'The project, financial year and revision cannot be changed on an existing financial report.']);
            }
            if (! $record->exists) {
                $data['revision_no'] = 1;
            }
        }
        if (in_array($table, ['financial_reports', 'financing_agreements', 'project_cost_versions', 'annual_financials', 'quarterly_financials'])) {
            $projectIds = [];
            foreach ([$record->getAttributes(), $data] as $values) {
                $projectIds[] = $values['project_id'] ?? (! empty($values['report_id']) ? FinancialReport::whereKey($values['report_id'])->value('project_id') : null);
            }
            if (Project::whereIn('id', array_filter($projectIds))->whereNotNull('archived_at')->exists()) {
                throw ValidationException::withMessages(['project_id' => 'Restore the project before changing its archived financial information.']);
            }
        }
        if ($table === 'roles') {
            abort_unless(! $record->exists || $request->user()->canGrantRole($record->id), 403);
            if (! $request->user()->isSuperAdmin()) {
                abort_if(DB::table('permissions')->whereIn('id', $data['permissions'] ?? [])->whereNotIn('id', DB::table('role_permissions')->where('role_id', $request->user()->role_id)->select('permission_id'))->exists(), 403);
            }
            if ($record->exists && in_array($record->name, ['Super Admin', 'Admin', 'Project Director']) && $data['name'] !== $record->name) {
                throw ValidationException::withMessages(['name' => 'Built-in role names cannot be changed. You can edit their permissions.']);
            }
        }
        if ($record instanceof User) {
            abort_unless(! $record->exists || ! $record->role_id || $request->user()->canGrantRole($record->role_id), 403);
            abort_unless(empty($data['role_id']) || $request->user()->canGrantRole((int) $data['role_id']), 403);
            if ($record->exists && $data['email'] !== $record->email) {
                $data['email_verified_at'] = null;
                $data['pending_email'] = null;
                $data['pending_email_token_hash'] = null;
                $data['pending_email_expires_at'] = null;
            }
        }
        if ($table === 'issues') {
            $project = Project::findOrFail($data['project_id']);
            $component = ! empty($data['component_id']) ? Component::findOrFail($data['component_id']) : null;
            $subcomponent = ! empty($data['subcomponent_id']) ? Subcomponent::with('component')->findOrFail($data['subcomponent_id']) : null;
            $pipelineStep = ! empty($data['pipeline_step_id']) ? ProjectPipelineStep::findOrFail($data['pipeline_step_id']) : null;
            if ($pipelineStep && ($project->phase !== 'Pipeline' || $pipelineStep->project_id !== $project->id || $component || $subcomponent)) {
                throw ValidationException::withMessages(['component_id' => 'Choose a pipeline component belonging to the selected project.']);
            }
            if (($component || $subcomponent) && ($project->phase !== 'Ongoing' || ($component && $component->project_id !== $project->id) || ($subcomponent && ($subcomponent->component->project_id !== $project->id || ($component && $subcomponent->component_id !== $component->id))))) {
                throw ValidationException::withMessages(['subcomponent_id' => 'Choose a component and subcomponent from the selected ongoing project.']);
            }
            if ($subcomponent) {
                $data['component_id'] = null;
            }
        }
        if ($request->user()->isProjectDirector()) {
            $candidate = clone $record;
            $candidate->forceFill($data);
            foreach (['project_id' => Project::class, 'component_id' => Component::class, 'pipeline_step_id' => ProjectPipelineStep::class, 'subcomponent_id' => Subcomponent::class, 'report_id' => FinancialReport::class] as $key => $model) {
                if ($candidate->$key) {
                    abort_unless(PortfolioAccess::scope($model::query())->whereKey($candidate->$key)->exists(), 403);
                }
            }
            foreach ($data['projects'] ?? [] as $projectId) {
                abort_unless($request->user()->ownsProject((int) $projectId), 403);
            }
        }

        if ($record->exists) {
            foreach (['project_id', 'component_id', 'pipeline_step_id', 'subcomponent_id', 'report_id', 'financial_year_id', 'period_id'] as $parentKey) {
                if (array_key_exists($parentKey, $data) && (string) ($data[$parentKey] ?? '') !== (string) ($record->$parentKey ?? '')) {
                    throw ValidationException::withMessages([$parentKey => 'Existing records cannot be moved to a different parent. Create a new record instead.']);
                }
            }
        }
        if ($record instanceof User && $record->id === $request->user()->id && isset($data['email']) && $data['email'] !== $record->email) {
            throw ValidationException::withMessages(['email' => 'Use your Account page to verify a change to your own email.']);
        }
        if ($record instanceof User && $record->id === $request->user()->id && (($data['account_status'] ?? 'Active') !== 'Active' || (int) ($data['role_id'] ?? 0) !== (int) $record->role_id)) {
            throw ValidationException::withMessages(['role_id' => 'Another administrator must change your own access.']);
        }
        if ($table === 'roles' && $record->id === $request->user()->role_id) {
            throw ValidationException::withMessages(['record' => 'Another administrator must change the role you are currently using.']);
        }
        if (in_array($table, ['components', 'subcomponents'])) {
            $project = $table === 'components' ? Project::findOrFail($data['project_id']) : Component::findOrFail($data['component_id'])->project;
            if ($project->phase !== 'Ongoing') {
                throw ValidationException::withMessages(['record' => 'Pipeline projects use predefined steps and cannot have components.']);
            }
            $parentEnd = $table === 'components' ? $project->current_end_date : Component::findOrFail($data['component_id'])->effective_end;
            if (! empty($data['baseline_end']) && $parentEnd && $data['baseline_end'] > $parentEnd->toDateString()) {
                throw ValidationException::withMessages(['baseline_end' => 'The deadline cannot exceed its parent deadline.']);
            }
            if ($record->exists) {
                $parentKey = $table === 'components' ? 'project_id' : 'component_id';
                if ((int) $data[$parentKey] !== (int) $record->$parentKey) {
                    throw ValidationException::withMessages([$parentKey => 'Existing components cannot be moved to another parent.']);
                }
                if ($record->baseline_end && ($data['baseline_end'] ?? null) !== $record->baseline_end->toDateString()) {
                    throw ValidationException::withMessages(['baseline_end' => 'The baseline is immutable. Use a deadline extension request.']);
                }
                if (! $record->baseline_end) {
                    $data['effective_end'] = $data['baseline_end'] ?? null;
                }
                $data['version'] = $record->version + 1;
            } else {
                $data['effective_end'] = $data['baseline_end'] ?? null;
            }
        }
        if (in_array($table, ['progress_updates', 'issues'])) {
            $component = ! empty($data['component_id']) ? Component::findOrFail($data['component_id']) : null;
            $subcomponent = ! empty($data['subcomponent_id']) ? Subcomponent::with('component')->findOrFail($data['subcomponent_id']) : null;
            if (($component && $subcomponent) || ($table === 'progress_updates' && ! $component && ! $subcomponent)) {
                throw ValidationException::withMessages(['component_id' => 'Choose one component OR one subcomponent.']);
            }
            $projectId = $component?->project_id ?? $subcomponent?->component->project_id;
            if ($table === 'issues' && $projectId && $projectId !== (int) $data['project_id']) {
                throw ValidationException::withMessages(['component_id' => 'The selected work must belong to this project.']);
            }
            if ($table === 'progress_updates') {
                ($component ?? $subcomponent)->newQuery()->whereKey(($component ?? $subcomponent)->id)->lockForUpdate()->firstOrFail();
                $data['entered_by'] = $request->user()->id;
                if (($data['status'] === 'Completed' && (float) $data['progress_percent'] !== 100.0) || ($data['status'] === 'Not started' && (float) $data['progress_percent'] !== 0.0)) {
                    throw ValidationException::withMessages(['progress_percent' => 'Completed work must be 100%; not-started work must be 0%.']);
                }
            } else {
                $data['resolved_at'] = in_array($data['status'], ['Resolved', 'Closed']) ? ($record->resolved_at ?? now()) : null;
            }
        }
        if ($record instanceof ProjectPipelineStep) {
            $project = Project::whereKey($data['project_id'])->lockForUpdate()->firstOrFail();
            if ($record->exists && $record->planned_date && array_key_exists('planned_date', $data) && $data['planned_date'] !== $record->planned_date->toDateString()) {
                throw ValidationException::withMessages(['planned_date' => 'Use a deadline extension request to change an existing pipeline deadline.']);
            }
            if (! empty($data['planned_date']) && $project->current_end_date && $data['planned_date'] > $project->current_end_date->toDateString()) {
                throw ValidationException::withMessages(['planned_date' => 'The step deadline cannot exceed the project closing date.']);
            }
            if (ProjectPipelineStep::where('project_id', $project->id)->where('template_id', $data['template_id'])->when($record->exists, fn ($query) => $query->whereKeyNot($record->id))->exists()) {
                throw ValidationException::withMessages(['template_id' => 'This pipeline step has already been added to this project. Edit the existing step instead.']);
            }
            $template = PipelineStepTemplate::findOrFail($data['template_id']);
            if (! $record->exists && ! $template->active) {
                throw ValidationException::withMessages(['template_id' => 'Select an active predefined component.']);
            }
            if ($record->exists && (int) $data['template_id'] !== $record->template_id) {
                throw ValidationException::withMessages(['template_id' => 'The predefined component cannot be changed. Edit its name instead.']);
            }
            $data['sort_order'] = $template->sort_order;
            if ($project->phase !== 'Pipeline') {
                throw ValidationException::withMessages(['record' => 'The project is now ongoing; its pipeline history is read-only.']);
            }
            if ($data['status'] === 'Done' && empty($data['completed_date'])) {
                throw ValidationException::withMessages(['completed_date' => 'Enter the completion date for a finished step.']);
            }
            if ($data['is_current']) {
                foreach (ProjectPipelineStep::where('project_id', $project->id)->whereKeyNot($record->id)->where('is_current', true)->get() as $previousStep) {
                    $previousStep->update(['is_current' => false]);
                }
            }
        }
        if ($record instanceof Meeting) {
            $data['version'] = ($record->version ?? 0) + 1;
        }
        if (in_array($table, ['financing_agreements', 'financial_reports'])) {
            if (Project::findOrFail($data['project_id'])->phase !== 'Ongoing') {
                throw ValidationException::withMessages(['project_id' => 'Financial information belongs to ongoing projects only.']);
            }
        }
        if (isset($data['report_id'])) {
            $report = FinancialReport::whereKey($data['report_id'])->lockForUpdate()->firstOrFail();

            if (isset($data['period_id']) && ReportingPeriod::findOrFail($data['period_id'])->financial_year_id !== $report->financial_year_id) {
                throw ValidationException::withMessages(['period_id' => 'The quarter must belong to the report financial year.']);
            }
            if (isset($data['currency_code']) && $data['currency_code'] !== $report->currency_code) {
                throw ValidationException::withMessages(['currency_code' => 'Use the currency selected for this financial report. Change it through Edit financial information.']);
            }
        }
        if ($record instanceof ReportingPeriod) {
            $year = FinancialYear::findOrFail($data['financial_year_id']);
            $expectedStart = $year->start_date->copy()->addMonths(((int) $data['quarter_no'] - 1) * 3);
            if ($data['start_date'] !== $expectedStart->toDateString() || $data['end_date'] !== $expectedStart->copy()->addMonths(3)->subDay()->toDateString()) {
                throw ValidationException::withMessages(['start_date' => 'Quarter dates must match the selected financial year and quarter number.']);
            }
        }

        return $data;
    }

    private function logRelations(Model $record, string $label, array $before, array $after): void
    {
        if (array_map('strval', $before) === array_map('strval', $after)) {
            return;
        }
        AuditLog::create(['actor_id' => auth()->id(), 'actor_name' => auth()->user()->name, 'action' => 'updated', 'module' => $record->getTable(), 'subject_id' => $record->id, 'subject_name' => ($record->name ?? 'Record #'.$record->id).' — '.$label, 'changes' => ['before' => $before, 'after' => $after]]);
    }

    public function archive(Request $request, string $id): RedirectResponse
    {
        $definition = $this->definition($request, true);
        abort_unless(isset($definition['fields']['active']), 404);
        $request->validate(['archived' => 'required|boolean']);
        $record = PortfolioAccess::scope($definition['model']::query())->findOrFail($id);
        $record->active = ! $request->boolean('archived');
        $record->save();

        return back()->with('success', $record->active ? 'Record restored.' : 'Record archived. Existing project links are retained.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $definition = $this->definition($request, true);
        abort_if(($definition['append_only'] ?? false) || ($definition['existing_only'] ?? false), 403);
        $record = PortfolioAccess::scope($definition['model']::query())->findOrFail($id);
        abort_if($record instanceof User && $record->role_id && ! $request->user()->canGrantRole($record->role_id), 403);
        if ($record instanceof User && $record->id === $request->user()->id) {
            throw ValidationException::withMessages(['record' => 'You cannot delete your own account.']);
        }
        if (in_array($record->getTable(), ['financial_reports', 'annual_financials', 'quarterly_financials', 'issues', 'roles'])) {
            throw ValidationException::withMessages(['record' => 'Keep this record for history; update its status instead.']);
        }
        try {
            DB::transaction(fn () => $record->delete());
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23503'])) {
                throw ValidationException::withMessages(['record' => 'This record is in use. Retain it to preserve project history.']);
            }
            throw $exception;
        }

        return PortfolioAccess::redirectToAllowed($request->route('module').'.index')->with('success', 'Record deleted.');
    }
}
