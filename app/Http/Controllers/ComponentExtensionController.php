<?php

namespace App\Http\Controllers;

use App\Models\Component;
use App\Models\ExtensionDecision;
use App\Models\ExtensionRequest;
use App\Models\PortfolioNotification;
use App\Models\ProjectPipelineStep;
use App\Models\Subcomponent;
use App\Models\User;
use App\PortfolioAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ComponentExtensionController extends Controller
{
    public function index(Request $request): View
    {
        $extensions = ExtensionRequest::with(['requester', 'reviewer', 'component.project', 'subcomponent.component.project', 'pipelineStep.project', 'decisions'])
            ->where(fn ($query) => $query->where('requester_id', $request->user()->id)->orWhere('reviewer_id', $request->user()->id))
            ->latest()->paginate(20);

        return view('projects.database-extensions', compact('extensions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['pipeline_step_id' => 'nullable|integer|exists:project_pipeline_steps,id', 'component_id' => 'nullable|integer|exists:components,id', 'subcomponent_id' => 'nullable|integer|exists:subcomponents,id', 'reviewer_id' => 'required|integer|exists:users,id', 'requested_end_date' => 'required|date', 'reason' => 'required|string|max:5000', 'impact' => 'nullable|string|max:5000']);
        if (count(array_filter(collect($data)->only(['component_id', 'subcomponent_id', 'pipeline_step_id'])->all())) !== 1) {
            throw ValidationException::withMessages(['component_id' => 'Choose exactly one pipeline step, component or subcomponent.']);
        }
        $projectId = DB::transaction(function () use ($request, $data): int {
            $key = ! empty($data['pipeline_step_id']) ? 'pipeline_step_id' : (! empty($data['component_id']) ? 'component_id' : 'subcomponent_id');
            $target = $this->lockTarget($data);
            $pipeline = $target instanceof ProjectPipelineStep;
            $project = $target instanceof Subcomponent ? $target->component->project : $target->project;
            $deadline = $pipeline ? $target->planned_date : $target->effective_end;
            if ($project->phase !== ($pipeline ? 'Pipeline' : 'Ongoing') || $project->lifecycle !== 'Active' || $project->archived_at) {
                throw ValidationException::withMessages(['record' => 'Extensions require an active project and a work item in its current phase.']);
            }
            abort_unless($request->user()->canAccessProject($project->id), 403);
            $reviewer = User::findOrFail($data['reviewer_id']);
            if ($reviewer->id === $request->user()->id || ! $reviewer->canApproveExtension($project->id)) {
                throw ValidationException::withMessages(['reviewer_id' => 'Select an assigned reviewer with extension approval permission who is neither the requester nor the project director.']);
            }
            if (! $deadline || ($pipeline ? $target->status === 'Done' : in_array($target->lifecycle, ['Completed', 'Cancelled'])) || $data['requested_end_date'] <= $deadline->toDateString()) {
                throw ValidationException::withMessages(['requested_end_date' => 'An unfinished work item needs a current deadline, and the requested date must be later.']);
            }
            $parentEnd = $target instanceof Subcomponent ? $target->component->effective_end : $project->current_end_date;
            if (! $parentEnd || $data['requested_end_date'] > $parentEnd->toDateString()) {
                throw ValidationException::withMessages(['requested_end_date' => 'Set or extend the parent deadline first; this date cannot exceed it.']);
            }
            if (ExtensionRequest::where($key, $target->id)->where('status', 'Pending')->exists()) {
                throw ValidationException::withMessages(['record' => 'This work item already has a pending extension request.']);
            }
            $extension = ExtensionRequest::create($data + ['requester_id' => $request->user()->id, 'old_end_date' => $deadline, 'target_version' => $target->version, 'status' => 'Pending']);
            PortfolioNotification::create(['recipient_id' => $reviewer->id, 'project_id' => $project->id, 'event_key' => 'extension:'.$extension->id.':requested', 'message' => 'Deadline extension requested for '.$target->name]);

            return $project->id;
        });

        return PortfolioAccess::redirectToAllowed('projects.show', ['project' => $projectId, 'tab' => ! empty($data['pipeline_step_id']) ? 'pipeline' : 'physical'])->with('success', 'Extension requested. The current deadline is unchanged pending approval.');
    }

    public function decide(Request $request, ExtensionRequest $extension): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:Approved,Rejected,Returned', 'remarks' => 'required|string|max:5000']);
        DB::transaction(function () use ($request, $extension, $data): void {
            $extension = ExtensionRequest::whereKey($extension->id)->lockForUpdate()->firstOrFail();
            $target = $this->lockTarget($extension->getAttributes());
            $pipeline = $target instanceof ProjectPipelineStep;
            $project = $target instanceof Subcomponent ? $target->component->project : $target->project;
            $deadline = $pipeline ? $target->planned_date : $target->effective_end;
            abort_unless($extension->reviewer_id === $request->user()->id && $extension->requester_id !== $request->user()->id && $request->user()->canApproveExtension($project->id), 403);
            if ($extension->status !== 'Pending') {
                throw ValidationException::withMessages(['record' => 'This request has already been decided or withdrawn.']);
            }
            if ($data['decision'] === 'Approved') {
                $parentEnd = $target instanceof Subcomponent ? $target->component->effective_end : $project->current_end_date;
                if ($project->phase !== ($pipeline ? 'Pipeline' : 'Ongoing') || $project->lifecycle !== 'Active' || $project->archived_at || $target->version !== $extension->target_version || $deadline?->toDateString() !== $extension->old_end_date->toDateString() || ! $parentEnd || $extension->requested_end_date->gt($parentEnd) || ($pipeline ? $target->status === 'Done' : in_array($target->lifecycle, ['Completed', 'Cancelled']))) {
                    throw ValidationException::withMessages(['record' => 'The work item or parent schedule has changed. Return this request and ask for a new one.']);
                }
                $target->update([($pipeline ? 'planned_date' : 'effective_end') => $extension->requested_end_date, 'version' => $target->version + 1]);
            }
            ExtensionDecision::create(['request_id' => $extension->id, 'reviewer_id' => $request->user()->id, 'sequence_no' => 1, 'decision' => $data['decision'], 'approved_end_date' => $data['decision'] === 'Approved' ? $extension->requested_end_date : null, 'remarks' => $data['remarks'], 'decided_at' => now()]);
            $extension->update(['status' => $data['decision']]);
            PortfolioNotification::create(['recipient_id' => $extension->requester_id, 'project_id' => $project->id, 'event_key' => 'extension:'.$extension->id.':decision', 'message' => 'Extension request '.$data['decision'].' for '.$target->name]);
        });

        return back()->with('success', 'Extension decision recorded.');
    }

    /** @param array<string, mixed> $data */
    private function lockTarget(array $data): Component|Subcomponent|ProjectPipelineStep
    {
        if (! empty($data['pipeline_step_id'])) {
            return ProjectPipelineStep::whereKey($data['pipeline_step_id'])->lockForUpdate()->firstOrFail();
        }

        return ! empty($data['component_id'])
            ? Component::whereKey($data['component_id'])->lockForUpdate()->firstOrFail()
            : Subcomponent::whereKey($data['subcomponent_id'])->lockForUpdate()->firstOrFail();
    }

    public function withdraw(Request $request, ExtensionRequest $extension): RedirectResponse
    {
        DB::transaction(function () use ($request, $extension): void {
            $extension = ExtensionRequest::whereKey($extension->id)->lockForUpdate()->firstOrFail();
            abort_unless($extension->requester_id === $request->user()->id && $request->user()->account_status === 'Active', 403);
            if ($extension->status !== 'Pending') {
                throw ValidationException::withMessages(['record' => 'Only pending requests can be withdrawn.']);
            }
            $extension->update(['status' => 'Withdrawn']);
        });

        return back()->with('success', 'Request withdrawn; the deadline is unchanged.');
    }
}
