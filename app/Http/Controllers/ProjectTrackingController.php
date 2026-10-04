<?php

namespace App\Http\Controllers;

use App\Models\TrackerRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectTrackingController extends Controller
{
    public function show(Request $request, TrackerRecord $record): View|JsonResponse
    {
        abort_unless($record->module === 'projects', 404);

        return $this->tracking($request, $record);
    }

    public function sampleShow(Request $request, string $sample): View|JsonResponse
    {
        return $this->tracking($request, $this->sampleRecord($sample), $sample);
    }

    public function details(TrackerRecord $record): View
    {
        abort_unless($record->module === 'projects', 404);

        return view('projects.details', ['record' => $record, 'trackingUrl' => route('projects.show', $record)]);
    }

    public function sampleDetails(string $sample): View
    {
        return view('projects.details', ['record' => $this->sampleRecord($sample), 'sample' => $sample, 'trackingUrl' => route('projects.sample-show', $sample)]);
    }

    public function download(TrackerRecord $record, string $document): StreamedResponse
    {
        abort_unless($record->module === 'projects' && ctype_digit($document), 404);
        $attachment = $record->data['attachments'][(int) $document] ?? null;
        $path = $attachment['path'] ?? '';
        abort_unless(str_starts_with($path, 'tracker-documents/') && ! str_contains($path, '..') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $attachment['title']);
    }

    private function sampleRecord(string $sample): TrackerRecord
    {
        $data = config('project_samples.'.$sample);
        abort_unless(is_array($data), 404);

        return new TrackerRecord(['module' => 'projects', 'data' => $data]);
    }

    private function tracking(Request $request, TrackerRecord $record, ?string $sample = null): View|JsonResponse
    {
        $stages = config('tracker.projects.fields.2.4');
        $projectKey = $sample ? 'sample:'.$sample : 'project:'.$record->id;
        $activities = $sample
            ? collect(config('tracker.projects.activity_templates'))->map(fn (array $template, string $key): TrackerRecord => new TrackerRecord(['module' => 'activities', 'data' => ['name' => $template['name'], 'stage' => $template['stage'], 'template_id' => $key, 'type' => 'Predefined', 'status' => 'Not Started']]))
            : TrackerRecord::where('module', 'activities')->where('data->project_id', (string) $record->id)->orderBy('id')->get();
        $templateOrder = array_flip(array_keys(config('tracker.projects.activity_templates')));
        $activities = $activities->sortBy(fn (TrackerRecord $activity): int => $templateOrder[$activity->data['template_id'] ?? ''] ?? PHP_INT_MAX);
        $rows = $activities->map(fn (TrackerRecord $activity): array => $this->activityRow($activity))->values();
        $groups = collect($stages)->map(fn (string $stage, int $index): array => ['name' => $stage, 'number' => $index + 1, 'activities' => $rows->where('stage', $stage)->values()]);
        $summary = ['total' => $rows->count(), 'on_track' => $rows->where('health', 'On track')->count(), 'off_track' => $rows->where('health', 'Off track')->count(), 'completed' => $rows->where('health', 'Completed')->count(), 'unscheduled' => $rows->where('health', 'Not scheduled')->count()];
        $applicable = $rows->where('health', '!=', 'Not applicable')->count();
        $progress = $applicable ? (int) round($summary['completed'] / $applicable * 100) : 0;
        $contexts = ['project' => 'Whole project'];
        foreach ($groups as $group) {
            $contexts['stage:'.$group['number']] = 'Stage '.$group['number'].' · '.$group['name'];
            foreach ($group['activities'] as $row) {
                $contexts['activity:'.$row['key']] = 'Stage '.$group['number'].' · '.$row['name'];
            }
        }
        $context = $request->string('context')->value();
        $context = array_key_exists($context, $contexts) ? $context : 'project';
        $comments = TrackerRecord::where('module', 'project_comments')->where('data->project_key', $projectKey)->orderBy('id')->get();
        $threads = $comments->filter(fn (TrackerRecord $comment): bool => empty($comment->data['parent_id']) && ($context === 'project' || $comment->data['context'] === $context))->reverse()->values();
        $replies = $comments->filter(fn (TrackerRecord $comment): bool => ! empty($comment->data['parent_id']))->groupBy('data.parent_id');

        $commentMetadata = $comments->map(fn (TrackerRecord $comment): array => ['id' => $comment->id, 'context' => $comment->data['context'], 'author_id' => $comment->data['author_id']])->values();
        $viewData = [
            'record' => $record, 'sample' => $sample, 'groups' => $groups, 'summary' => $summary,
            'progress' => $progress, 'contexts' => $contexts, 'context' => $context,
            'threads' => $threads, 'replies' => $replies,
            'projectKey' => $projectKey, 'commentMetadata' => $commentMetadata,
            'trackingUrl' => $sample ? route('projects.sample-show', $sample) : route('projects.show', $record),
            'detailsUrl' => $sample ? route('projects.sample-details', $sample) : route('projects.details', $record),
            'commentsUrl' => $sample ? route('projects.sample-comments.store', $sample) : route('projects.comments.store', $record),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'comments' => $commentMetadata,
                'html' => view('projects.partials.comment-threads', $viewData)->render(),
            ])->header('Cache-Control', 'no-store');
        }

        return view('projects.show', $viewData);
    }

    /** @return array{key: string, record: TrackerRecord, name: string, stage: string, health: string, status: string, start: string, due: string, officer: string, remarks: string} */
    private function activityRow(TrackerRecord $activity): array
    {
        $data = $activity->data;
        $status = $data['status'] ?? 'Not Started';
        $due = $data['due'] ?? '';
        $health = match (true) {
            $status === 'Not Applicable' => 'Not applicable',
            $status === 'Completed', ! empty($data['actual_completion']) => 'Completed',
            $status === 'On Hold', $due !== '' && $due < now('Asia/Karachi')->toDateString() => 'Off track',
            $due !== '' => 'On track',
            default => 'Not scheduled',
        };

        return ['key' => (string) ($activity->id ?? $data['template_id']), 'record' => $activity, 'name' => $data['name'], 'stage' => $data['stage'] ?? 'Concept', 'health' => $health, 'status' => $status, 'start' => $data['start'] ?? '', 'due' => $due, 'officer' => $data['officer'] ?? '', 'remarks' => $data['remarks'] ?? ''];
    }
}
