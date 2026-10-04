<?php

namespace App;

use App\Models\TrackerRecord;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardUpdates
{
    public function __construct(private ProjectPortfolio $portfolio) {}

    public function compose(View $view): void
    {
        $issueProjects = $this->portfolio->rows()->where('has_issues', true)->values();
        $view->with('headerIssueCount', $issueProjects->count())->with('issueProjects', $issueProjects);
    }

    public function snapshot(Request $request): array
    {
        $meetings = config('dashboard.presentation') ? collect() : TrackerRecord::where('module', 'meetings')->latest('id')->get();
        $demo = $meetings->isEmpty();
        if ($demo) {
            $meetings = collect(config('meeting_samples'))->map(fn (array $data, int $id): TrackerRecord => new TrackerRecord(['module' => 'meetings', 'data' => array_merge($data, ['sample_id' => $id])]));
        }
        $seen = $request->user()
            ? TrackerRecord::where('module', 'meeting_reads')->where('data->user_id', (string) $request->user()->id)->get()->pluck('data.version', 'data.meeting_key')->all()
            : $request->session()->get('meeting_reads', []);
        $meetingRows = $meetings->map(function (TrackerRecord $meeting) use ($demo, $seen): array {
            $data = $meeting->data;
            $key = $demo ? 'sample:'.$data['sample_id'] : 'meeting:'.$meeting->id;
            $date = ! empty($data['date']) ? CarbonImmutable::parse($data['date'], 'Asia/Karachi') : null;

            return ['key' => $key, 'name' => $data['name'], 'date' => $date, 'location' => $data['venue'] ?? 'Location not set', 'status' => $data['status'] ?? 'Scheduled', 'unseen' => ($seen[$key] ?? '') !== $this->version($meeting), 'url' => $demo ? route('meetings.sample-show', $data['sample_id']) : route('meetings.show', $meeting), 'demo' => $demo, 'order' => $meeting->updated_at?->timestamp ?? $date?->timestamp ?? 0];
        });

        return ['unseenMeetings' => $meetingRows->where('unseen', true)->sortByDesc('order')->values(), 'upcomingMeetings' => $meetingRows->filter(fn (array $meeting): bool => $meeting['status'] === 'Scheduled' && $meeting['date']?->greaterThanOrEqualTo(now('Asia/Karachi')))->sortBy('date')->values(), 'recentComments' => $this->comments($request)];
    }

    public function markViewed(Request $request, TrackerRecord $meeting, ?int $sample = null): void
    {
        $key = $sample ? 'sample:'.$sample : 'meeting:'.$meeting->id;
        $version = $this->version($meeting);
        if (! $request->user()) {
            $seen = $request->session()->get('meeting_reads', []);
            $seen[$key] = $version;
            $request->session()->put('meeting_reads', $seen);

            return;
        }
        $data = ['user_id' => (string) $request->user()->id, 'meeting_key' => $key, 'version' => $version];
        $read = TrackerRecord::where('module', 'meeting_reads')->where('data->user_id', $data['user_id'])->where('data->meeting_key', $key)->first();
        if ($read) {
            $read->update(['data' => $data]);
        } else {
            TrackerRecord::create(['module' => 'meeting_reads', 'data' => $data]);
        }
    }

    public function markCommentsRead(Request $request, array $ids): void
    {
        $ids = TrackerRecord::where('module', 'project_comments')->whereIn('id', $ids)->pluck('id')->all();
        if (! $request->user()) {
            $request->session()->put('comment_reads', array_values(array_unique(array_merge($request->session()->get('comment_reads', []), $ids))));

            return;
        }
        $userId = (string) $request->user()->id;
        Cache::lock('comment-reads:'.$userId, 10)->block(5, function () use ($userId, $ids): void {
            $record = TrackerRecord::where('module', 'comment_reads')->where('data->user_id', $userId)->first();
            $data = ['user_id' => $userId, 'comment_ids' => array_values(array_unique(array_merge($record?->data['comment_ids'] ?? [], $ids)))];
            if ($record) {
                $record->update(['data' => $data]);
            } else {
                TrackerRecord::create(['module' => 'comment_reads', 'data' => $data]);
            }
        });
    }

    private function version(TrackerRecord $meeting): string
    {
        $data = $meeting->data;
        unset($data['sample_id']);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function comments(Request $request): Collection
    {
        if (config('dashboard.presentation')) {
            return collect(config('dashboard.projects'))->take(4)->map(fn (array $project, int $index): array => [
                'id' => $index + 1, 'project' => $project['name'], 'author' => $project['officer'],
                'body' => 'Project update shared for review.', 'reply' => $index % 2 === 1, 'time' => 'Demo update',
                'url' => route('dashboard.projects.show', $project['reference']).'#project-discussion',
            ]);
        }
        $seen = $request->user()
            ? (TrackerRecord::where('module', 'comment_reads')->where('data->user_id', (string) $request->user()->id)->first()?->data['comment_ids'] ?? [])
            : $request->session()->get('comment_reads', []);
        $query = TrackerRecord::where('module', 'project_comments')->whereNotIn('id', $seen);
        if ($request->user()) {
            $query->where('data->author_id', '!=', (string) $request->user()->id);
        }
        $comments = $query->latest('id')->get();
        $ids = $comments->map(fn (TrackerRecord $comment): string => str_starts_with($comment->data['project_key'] ?? '', 'project:') ? substr($comment->data['project_key'], 8) : '')->filter();
        $projects = TrackerRecord::where('module', 'projects')->whereIn('id', $ids)->get()->keyBy('id');

        return $comments->map(function (TrackerRecord $comment) use ($projects): ?array {
            [$type, $id] = array_pad(explode(':', $comment->data['project_key'] ?? '', 2), 2, '');
            $project = $type === 'project' ? $projects->get($id) : null;
            $sample = $type === 'sample' ? config('project_samples.'.$id) : null;
            if (! $project && ! $sample) {
                return null;
            }
            $url = $project ? route('projects.show', $project) : route('projects.sample-show', $id);

            return ['id' => $comment->id, 'project' => $project?->data['name'] ?? $sample['name'], 'author' => $comment->data['author_name'] ?? 'Team member', 'body' => $comment->data['body'] ?? '', 'reply' => ! empty($comment->data['parent_id']), 'time' => $comment->created_at?->diffForHumans(), 'url' => $url.'?context=project#project-comments'];
        })->filter()->values();
    }
}
