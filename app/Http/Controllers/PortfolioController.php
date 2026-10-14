<?php

namespace App\Http\Controllers;

use App\DatabasePortfolio;
use App\Models\AuditLog;
use App\Models\District;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\ExtensionRequest;
use App\Models\Organization;
use App\Models\PipelineStepTemplate;
use App\Models\Project;
use App\Models\ProjectPhaseHistory;
use App\Models\ProjectScheduleRevision;
use App\Models\Sector;
use App\Models\User;
use App\PortfolioAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioController extends Controller
{
    public function show(Request $request, Project $project, DatabasePortfolio $portfolio): View
    {
        $request->validate(['tab' => 'nullable|in:general,physical,pipeline,financial', 'year' => 'nullable|integer', 'report' => 'nullable|integer', 'quarter' => 'nullable|integer|min:1|max:4']);
        $project->load(['sector', 'district', 'districts', 'organizations', 'contacts', 'costVersions', 'components.subcomponents', 'pipelineSteps', 'issues', 'financialReports.financialYear', 'financialReports.annuals', 'financialReports.quarters.period', 'comments.author']);
        $row = $portfolio->row($project);
        $tab = $request->input('tab', 'general');
        if ($project->phase === 'Pipeline' && $tab === 'financial') {
            $tab = 'pipeline';
        }
        if (in_array($tab, ['physical', 'pipeline'])) {
            $tab = $project->phase === 'Pipeline' ? 'pipeline' : 'physical';
        }
        abort_if($tab === 'financial' && ! $request->user()->hasPortfolioPermission('financial-reports.view'), 403);
        abort_if(in_array($tab, ['physical', 'pipeline']) && ! $request->user()->hasPortfolioPermission($tab === 'physical' ? 'activities.view' : 'pipeline-steps.view'), 403);
        if (! $request->user()->hasPortfolioPermission('issues.view')) {
            $project->setRelation('issues', collect());
        }
        if (! $request->user()->hasPortfolioPermission('comments.view')) {
            $project->setRelation('comments', collect());
        }
        foreach (['costVersions' => 'project-costs.view', 'financialReports' => 'financial-reports.view', 'components' => 'activities.view', 'pipelineSteps' => 'pipeline-steps.view'] as $relation => $permission) {
            if (! $request->user()->hasPortfolioPermission($permission)) {
                $project->setRelation($relation, collect());
            }
        }
        foreach ($project->components as $component) {
            if (! $request->user()->hasPortfolioPermission('subcomponents.view')) {
                $component->setRelation('subcomponents', collect());
            }
        }
        foreach ($project->financialReports as $financialReport) {
            foreach (['annuals' => 'annual-financials.view', 'quarters' => 'quarterly-financials.view'] as $relation => $permission) {
                if (! $request->user()->hasPortfolioPermission($permission)) {
                    $financialReport->setRelation($relation, collect());
                }
            }
        }
        $agreements = $request->user()->hasPortfolioPermission('financing-agreements.view') ? $project->financingAgreements()->get() : collect();
        $reports = $project->financialReports;
        $yearId = $request->integer('year') ?: $reports->first()?->financial_year_id;
        $yearReports = $reports->where('financial_year_id', $yearId);
        $report = $request->filled('report') ? $yearReports->firstWhere('id', $request->integer('report')) : $yearReports->first();
        abort_if($request->filled('report') && ! $report, 404);
        $quarters = $report?->quarters->when($request->filled('quarter'), fn ($items) => $items->filter(fn ($item) => $item->period->quarter_no === $request->integer('quarter'))) ?? collect();
        $extensions = ExtensionRequest::with(['requester', 'reviewer', 'component', 'subcomponent.component', 'pipelineStep', 'decisions'])->where(function ($query) use ($project): void {
            $query->whereHas('pipelineStep', fn ($query) => $query->where('project_id', $project->id))->orWhereHas('component', fn ($query) => $query->where('project_id', $project->id))->orWhereHas('subcomponent.component', fn ($query) => $query->where('project_id', $project->id));
        })->latest()->get();
        $reviewers = User::where('account_status', 'Active')->whereIn('id', DB::table('project_assignments')->where('project_id', $project->id)->where('can_approve_extension', true)->where(fn ($query) => $query->whereNull('active_to')->orWhere('active_to', '>=', today()->toDateString()))->select('user_id'))->get()->filter(fn (User $user): bool => $user->canApproveExtension($project->id));
        if (! $request->user()->hasPortfolioPermission('extensions.view')) {
            $extensions = collect();
        }
        $documents = Document::whereIn('id', DocumentLink::where('project_id', $project->id)->select('document_id'))->get();
        $schedule = ProjectScheduleRevision::where('project_id', $project->id)->orderByDesc('revision_no')->get();
        $seenComments = $request->user() ? DB::table('comment_reads')->where('user_id', $request->user()->id)->pluck('version', 'comment_id')->all() : $request->session()->get('portfolio_comment_reads', []);
        $unreadCommentCount = $project->comments->filter(fn ($comment): bool => $comment->author_id !== $request->user()?->id && ($seenComments[$comment->id] ?? 0) !== $comment->version)->count();

        return view('projects.database-overview', compact('project', 'row', 'tab', 'reports', 'yearId', 'yearReports', 'report', 'quarters', 'extensions', 'reviewers', 'documents', 'schedule', 'unreadCommentCount', 'agreements'));
    }

    public function create(Request $request): View
    {
        return $this->form($request, new Project);
    }

    public function edit(Request $request, Project $project): View
    {
        return $this->form($request, $project);
    }

    private function form(Request $request, Project $project): View
    {
        abort_unless($request->user()->hasPortfolioPermission($project->exists ? 'projects.update' : 'projects.create'), 403);

        return view('projects.database-form', ['pipelineTemplates' => PipelineStepTemplate::where(fn ($query) => $query->where('active', true)->orWhereIn('id', $project->pipelineSteps()->select('template_id')))->orderBy('sort_order')->get(), 'extensionApprovers' => User::where('account_status', 'Active')->whereIn('role_id', DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')->where('permissions.code', 'extensions.approve')->select('role_id'))->orderBy('name')->get(), 'currencies' => DB::table('currencies')->orderBy('code')->get(), 'project' => $project, 'sectors' => Sector::where(fn ($query) => $query->where('active', true)->orWhere('id', $project->sector_id))->orderBy('name')->get(), 'districts' => District::where(fn ($query) => $query->where('active', true)->orWhere('id', $project->district_id)->orWhereIn('id', $project->districts->pluck('id')))->orderBy('name')->get(), 'organizations' => Organization::where(fn ($query) => $query->where('active', true)->orWhereIn('id', $project->organizations->pluck('id')))->orderBy('name')->get(), 'users' => User::orderBy('name')->get(), 'assignments' => $project->exists ? DB::table('project_assignments')->where('project_id', $project->id)->get() : collect()]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new Project);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        return $this->save($request, $project);
    }

    public function archive(Request $request, Project $project): RedirectResponse
    {
        abort_unless($request->user()->hasPortfolioPermission('projects.archive'), 403);
        $request->validate(['archived' => 'required|boolean']);
        $project->archived_at = $request->boolean('archived') ? now() : null;
        $project->save();

        return back()->with('success', $project->archived_at ? 'Project archived. Its history is retained.' : 'Project restored.');
    }

    private function save(Request $request, Project $project): RedirectResponse
    {
        abort_unless($request->user()->hasPortfolioPermission($project->exists ? 'projects.update' : 'projects.create'), 403);
        $data = $request->validate(['reference_code' => ['required', 'string', 'max:100', Rule::unique('projects')->ignore($project->id)], 'name' => 'required|string|max:255', 'objective' => 'nullable|string|max:20000', 'phase' => 'required|in:Pipeline,Ongoing', 'lifecycle' => 'required|in:Active,Completed,Cancelled', 'sector_id' => 'required|exists:sectors,id', 'district_id' => 'required_without:district_ids|nullable|exists:districts,id', 'district_ids' => 'sometimes|required|array|min:1', 'district_ids.*' => 'required|integer|distinct|exists:districts,id', 'project_director_id' => 'nullable|exists:users,id', 'is_flagship' => 'boolean', 'start_date' => 'nullable|date', 'current_end_date' => 'nullable|date|after_or_equal:start_date', 'approval_forum' => 'nullable|string|max:255', 'approval_date' => 'nullable|date', 'funding_type' => 'nullable|in:Grant,Loan,Govt. share,Mixed', 'implementing_agency' => 'nullable|string|max:255', 'change_reason' => 'nullable|string|max:3000', 'donors' => 'array', 'donors.*' => 'integer|exists:organizations,id', 'agency' => 'nullable|exists:organizations,id', 'members' => 'array', 'members.*' => 'integer|exists:users,id', 'approvers' => 'array', 'approvers.*' => 'integer|exists:users,id']);
        if (isset($data['district_ids'])) {
            $data['district_id'] = $data['district_ids'][0];
        }
        foreach ($data['approvers'] ?? [] as $approverId) {
            if (! User::findOrFail($approverId)->hasPortfolioPermission('extensions.approve')) {
                throw ValidationException::withMessages(['approvers' => 'Select a user with extension approval permission.']);
            }
            if ((int) $approverId === (int) ($data['project_director_id'] ?? $project->project_director_id)) {
                throw ValidationException::withMessages(['approvers' => 'The project director cannot approve extensions for their own project.']);
            }
        }
        $creation = $request->validate([
            'pipeline_template_id' => ['exclude_unless:phase,Pipeline', 'nullable', Rule::exists('pipeline_step_templates', 'id')->where('active', true)],
            'components' => 'exclude_unless:phase,Ongoing|sometimes|array|max:30',
            'components.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('components', 'id')->where('project_id', $project->id ?? 0)],
            'components.*' => 'array:id,name,start_date,baseline_end,responsible_office,remarks,subcomponents',
            'components.*.name' => 'required|string|max:255',
            'components.*.start_date' => 'nullable|date_format:Y-m-d',
            'components.*.baseline_end' => 'nullable|date_format:Y-m-d',
            'components.*.responsible_office' => 'nullable|string|max:255',
            'components.*.remarks' => 'nullable|string|max:10000',
            'components.*.subcomponents' => 'sometimes|array|max:30',
            'components.*.subcomponents.*' => 'array:name,start_date,baseline_end,responsible_office,remarks',
            'components.*.subcomponents.*.name' => 'required|string|max:255',
            'components.*.subcomponents.*.start_date' => 'nullable|date_format:Y-m-d',
            'components.*.subcomponents.*.baseline_end' => 'nullable|date_format:Y-m-d',
            'components.*.subcomponents.*.responsible_office' => 'nullable|string|max:255',
            'components.*.subcomponents.*.remarks' => 'nullable|string|max:10000',
            'pipeline_components' => 'exclude_unless:phase,Pipeline|sometimes|array|min:1|max:100',
            'pipeline_components.*' => 'array:template_id,start_date,planned_date,responsible_office,remarks',
            'pipeline_components.*.template_id' => ['required', 'integer', 'distinct', Rule::exists('pipeline_step_templates', 'id')],
            'pipeline_components.*.start_date' => 'nullable|date_format:Y-m-d',
            'pipeline_components.*.planned_date' => 'nullable|date_format:Y-m-d',
            'pipeline_components.*.responsible_office' => 'nullable|string|max:255',
            'pipeline_components.*.remarks' => 'nullable|string|max:10000',
            'initial_cost' => 'nullable|numeric|min:0|max:999999999999999.99',
            'cost_currency' => 'required_with:initial_cost|nullable|exists:currencies,code',
        ]);
        if ($project->exists && isset($creation['pipeline_template_id'])) {
            abort_unless($request->user()->hasPortfolioPermission('pipeline-steps.update'), 403);
        }
        if ($project->exists && isset($creation['initial_cost'])) {
            throw ValidationException::withMessages(['record' => 'Manage existing components and cost revisions from the project detail page.']);
        }
        if (! empty($creation['components'])) {
            if (collect($creation['components'])->contains(fn (array $item): bool => empty($item['id']))) {
                abort_unless($request->user()->hasPortfolioPermission('activities.create'), 403);
            }
            foreach ($creation['components'] as $index => $component) {
                if (! empty($component['id'])) {
                    $existing = $project->components()->findOrFail($component['id']);
                    $component['start_date'] = $existing->start_date?->toDateString();
                    $component['baseline_end'] = $existing->effective_end?->toDateString();
                }
                $this->validateInitialDates($component, $data['start_date'] ?? null, $data['current_end_date'] ?? null, 'components.'.$index);
                if (! empty($component['subcomponents'])) {
                    abort_unless($request->user()->hasPortfolioPermission('subcomponents.create'), 403);
                }
                foreach ($component['subcomponents'] ?? [] as $subIndex => $subcomponent) {
                    $this->validateInitialDates($subcomponent, $component['start_date'] ?? $data['start_date'] ?? null, $component['baseline_end'] ?? $data['current_end_date'] ?? null, 'components.'.$index.'.subcomponents.'.$subIndex);
                }
            }
        }
        if (isset($creation['initial_cost'])) {
            abort_unless($request->user()->hasPortfolioPermission('project-costs.create'), 403);
        }
        if ($project->exists && $project->phase === 'Ongoing' && $data['phase'] !== 'Ongoing') {
            throw ValidationException::withMessages(['phase' => 'An ongoing project cannot return to Pipeline.']);
        }
        DB::transaction(function () use ($request, $data, $project, $creation): void {
            if ($project->exists) {
                $project->setRawAttributes(Project::whereKey($project->id)->lockForUpdate()->firstOrFail()->getAttributes(), true);
            }
            $oldPhase = $project->phase;
            $oldEnd = $project->current_end_date?->toDateString();
            $changedSchedule = $project->exists && $oldEnd !== ($data['current_end_date'] ?? null);
            if (($changedSchedule || ($project->exists && $oldPhase !== $data['phase'])) && empty($data['change_reason'])) {
                throw ValidationException::withMessages(['change_reason' => 'Explain the phase or closing-date change.']);
            }
            if ($changedSchedule && $project->components()->whereNotNull('effective_end')->where('effective_end', '>', $data['current_end_date'] ?? '0001-01-01')->exists()) {
                throw ValidationException::withMessages(['current_end_date' => 'The project cannot end before an active component deadline.']);
            }
            if ($changedSchedule && $project->phase === 'Pipeline' && $project->pipelineSteps()->where('status', '!=', 'Done')->whereNotNull('planned_date')->where('planned_date', '>', $data['current_end_date'] ?? '0001-01-01')->exists()) {
                throw ValidationException::withMessages(['current_end_date' => 'The project cannot end before an unfinished pipeline step deadline.']);
            }
            $project->fill(collect($data)->except(['district_ids', 'donors', 'agency', 'members', 'approvers', 'change_reason', 'project_director_id', 'is_flagship'])->all());
            if ($request->user()->hasPortfolioPermission('projects.flagship')) {
                $project->is_flagship = $request->boolean('is_flagship');
            } elseif ($request->has('is_flagship')) {
                abort(403);
            }
            if ($request->user()->isProjectDirector() && ! $project->exists) {
                $project->project_director_id = $request->user()->id;
            } elseif ($request->user()->hasPortfolioPermission('projects.assign')) {
                $project->project_director_id = $data['project_director_id'] ?? null;
            } elseif ($request->has('project_director_id')) {
                abort(403);
            }
            if (! $project->exists) {
                $project->original_end_date = $data['current_end_date'] ?? null;
            }
            $project->save();
            if (isset($data['district_ids']) || ! $project->districts()->exists()) {
                $beforeDistricts = $project->districts()->pluck('districts.id')->all();
                $project->districts()->sync($data['district_ids'] ?? [$data['district_id']]);
                if ($beforeDistricts !== ($data['district_ids'] ?? [$data['district_id']])) {
                    AuditLog::create(['actor_id' => $request->user()->id, 'actor_name' => $request->user()->name, 'action' => 'updated', 'module' => 'projects', 'subject_id' => $project->id, 'subject_name' => $project->name.' districts', 'changes' => ['before' => $beforeDistricts, 'after' => $data['district_ids'] ?? [$data['district_id']]]]);
                }
            }
            if ($changedSchedule && $oldEnd && $project->current_end_date) {
                ProjectScheduleRevision::create(['project_id' => $project->id, 'revision_no' => (int) ProjectScheduleRevision::where('project_id', $project->id)->max('revision_no') + 1, 'previous_end' => $oldEnd, 'revised_end' => $project->current_end_date, 'reason' => $data['change_reason'], 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            }
            if ($oldPhase && $oldPhase !== $project->phase) {
                ProjectPhaseHistory::create(['project_id' => $project->id, 'from_phase' => $oldPhase, 'to_phase' => $project->phase, 'changed_by' => $request->user()->id, 'changed_at' => now(), 'reason' => $data['change_reason']]);
            }
            if ($project->phase === 'Pipeline') {
                $templates = PipelineStepTemplate::orderBy('sort_order')->get()->keyBy('id');
                if (isset($creation['pipeline_components'])) {
                    $previousOrder = 0;
                    foreach ($creation['pipeline_components'] as $index => $stepData) {
                        $template = $templates->get($stepData['template_id']);
                        $step = $project->pipelineSteps()->where('template_id', $template->id)->first();
                        abort_unless($request->user()->hasPortfolioPermission('pipeline-steps.'.($step ? 'update' : 'create')), 403);
                        if ($step?->planned_date && array_key_exists('planned_date', $stepData) && $stepData['planned_date'] !== $step->planned_date->toDateString()) {
                            throw ValidationException::withMessages(['pipeline_components.'.$index.'.planned_date' => 'Use a deadline extension request to change an existing pipeline deadline.']);
                        }
                        if ((! $template->active && ! $step) || $template->sort_order <= $previousOrder) {
                            throw ValidationException::withMessages(['pipeline_components.'.$index.'.template_id' => 'Select each active predefined component once, in its listed order.']);
                        }
                        $previousOrder = $template->sort_order;
                        $this->validateInitialDates(['start_date' => $stepData['start_date'] ?? null, 'baseline_end' => $stepData['planned_date'] ?? null], $data['start_date'] ?? null, $data['current_end_date'] ?? null, 'pipeline_components.'.$index);
                        $project->pipelineSteps()->updateOrCreate(['template_id' => $template->id], collect($stepData)->except('template_id')->all() + ['name' => $step?->name ?? $template->name, 'sort_order' => $template->sort_order]);
                    }
                } elseif (! $project->pipelineSteps()->exists() && $request->user()->hasPortfolioPermission('pipeline-steps.create')) {
                    $active = $templates->where('active', true);
                    if ($active->isEmpty()) {
                        throw ValidationException::withMessages(['phase' => 'Add an active pipeline component template first.']);
                    }
                    foreach ($active as $template) {
                        $project->pipelineSteps()->create(['template_id' => $template->id, 'name' => $template->name, 'sort_order' => $template->sort_order]);
                    }
                }
                $currentId = $creation['pipeline_template_id'] ?? $project->pipelineSteps()->where('is_current', true)->value('template_id') ?? $project->pipelineSteps()->first()?->template_id;
                if ($currentId !== null) {
                    if (! $project->pipelineSteps()->where('template_id', $currentId)->exists()) {
                        throw ValidationException::withMessages(['pipeline_template_id' => 'Select a current component included in this project.']);
                    }
                    $project->pipelineSteps()->where('is_current', true)->update(['is_current' => false]);
                    $project->pipelineSteps()->where('template_id', $currentId)->firstOrFail()->update(['is_current' => true]);
                }
            }
            $sequence = (int) $project->components()->max('sequence_no');
            foreach ($creation['components'] ?? [] as $componentData) {
                $component = ! empty($componentData['id'])
                    ? $project->components()->findOrFail($componentData['id'])
                    : $project->components()->create(collect($componentData)->except(['id', 'subcomponents'])->all() + ['sequence_no' => ++$sequence, 'effective_end' => $componentData['baseline_end'] ?? null]);
                $subSequence = (int) $component->subcomponents()->max('sequence_no');
                foreach ($componentData['subcomponents'] ?? [] as $subcomponentData) {
                    $component->subcomponents()->create($subcomponentData + ['sequence_no' => ++$subSequence, 'effective_end' => $subcomponentData['baseline_end'] ?? null]);
                }
            }
            if (isset($creation['initial_cost'])) {
                $project->costVersions()->create(['version_no' => 1, 'currency_code' => $creation['cost_currency'], 'total_cost' => $creation['initial_cost']]);
            }
            $beforeRelations = [
                'organizations' => DB::table('project_organizations')->where('project_id', $project->id)->orderBy('organization_id')->orderBy('role')->get()->toArray(),
                'assignments' => DB::table('project_assignments')->where('project_id', $project->id)->orderBy('user_id')->get()->toArray(),
            ];
            DB::table('project_organizations')->where('project_id', $project->id)->whereIn('role', ['donor', 'implementing'])->delete();
            foreach (array_unique($data['donors'] ?? []) as $donor) {
                DB::table('project_organizations')->insert(['project_id' => $project->id, 'organization_id' => $donor, 'role' => 'donor']);
            }
            if (! empty($data['agency'])) {
                DB::table('project_organizations')->insert(['project_id' => $project->id, 'organization_id' => $data['agency'], 'role' => 'implementing']);
            }
            if ($request->user()->hasPortfolioPermission('projects.assign')) {
                DB::table('project_assignments')->where('project_id', $project->id)->delete();
                foreach (array_unique(array_merge($data['members'] ?? [], $data['approvers'] ?? [])) as $member) {
                    DB::table('project_assignments')->insert(['project_id' => $project->id, 'user_id' => $member, 'assignment_role' => 'Project team', 'can_approve_extension' => in_array($member, $data['approvers'] ?? [])]);
                }
            }
            $afterRelations = [
                'organizations' => DB::table('project_organizations')->where('project_id', $project->id)->orderBy('organization_id')->orderBy('role')->get()->toArray(),
                'assignments' => DB::table('project_assignments')->where('project_id', $project->id)->orderBy('user_id')->get()->toArray(),
            ];
            if (json_encode($beforeRelations) !== json_encode($afterRelations)) {
                AuditLog::create(['actor_id' => $request->user()->id, 'actor_name' => $request->user()->name, 'action' => 'updated', 'module' => 'projects', 'subject_id' => $project->id, 'subject_name' => $project->name.' — organizations and assignments', 'changes' => ['before' => $beforeRelations, 'after' => $afterRelations]]);
            }
        });

        return PortfolioAccess::redirectToAllowed('projects.show', ['project' => $project])->with('success', 'Project saved.');
    }

    /** @param array<string, mixed> $item */
    private function validateInitialDates(array $item, ?string $parentStart, ?string $parentEnd, string $key): void
    {
        $start = $item['start_date'] ?? null;
        $end = $item['baseline_end'] ?? null;
        if ($start && $parentStart && $start < $parentStart) {
            throw ValidationException::withMessages([$key.'.start_date' => 'The start date cannot be earlier than its parent start date.']);
        }
        if (($end && ($start ?? $parentStart) && $end < ($start ?? $parentStart)) || ($parentEnd && (($end && $end > $parentEnd) || ($start && $start > $parentEnd)))) {
            throw ValidationException::withMessages([$key.'.baseline_end' => 'Component dates must be in order and within the parent schedule.']);
        }
    }

    public function comment(Request $request, Project $project): RedirectResponse
    {
        abort_unless($request->user()->canAccessProject($project->id), 403);
        $data = $request->validate(['body' => 'required|string|max:2000', 'parent_id' => ['nullable', Rule::exists('comments', 'id')->where('project_id', $project->id)]]);
        $project->comments()->create($data + ['author_id' => $request->user()->id]);

        return ($request->user()->hasPortfolioPermission('projects.view') ? redirect(route('projects.show', $project).'#project-discussion') : to_route('account.edit'))->with('success', 'Comment posted.');
    }

    public function upload(Request $request, Project $project): RedirectResponse
    {
        abort_unless($request->user()->canAccessProject($project->id), 403);
        $data = $request->validate(['title' => 'required|string|max:255', 'document' => 'required|file|mimes:pdf,docx,xlsx,jpg,jpeg,png|max:20480']);
        $file = $request->file('document');
        $path = $file->store('project-documents', 'local');
        try {
            DB::transaction(function () use ($data, $request, $file, $path, $project): void {
                $document = Document::create(['title' => $data['title'], 'path' => $path, 'disk' => 'local', 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'uploaded_by' => $request->user()->id]);
                DocumentLink::create(['document_id' => $document->id, 'project_id' => $project->id]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Document attached.');
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $projectId = DocumentLink::where('document_id', $document->id)->value('project_id');
        abort_unless($projectId && $request->user()->canAccessProject($projectId), 403);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path);
    }

    public function logs(Request $request): View
    {
        abort_unless($request->user()->hasPortfolioPermission('logs.view'), 403);

        return view('projects.database-logs', ['logs' => AuditLog::latest()->paginate(30)]);
    }
}
