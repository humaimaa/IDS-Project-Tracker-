<?php

namespace App;

use App\Models\Comment;
use App\Models\Meeting;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabasePortfolio
{
    public function rows(): Collection
    {
        return PortfolioAccess::scope(Project::query())->when(request()->routeIs('projects.index') && request('archived') === '1', fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))->with(['sector', 'district', 'districts', 'organizations', 'contacts', 'costVersions', 'issues', 'components.subcomponents', 'pipelineSteps'])->get()->map(fn (Project $project): array => $this->row($project));
    }

    public function row(Project $project): array
    {
        $reasons = $project->issues->reject(fn ($issue): bool => in_array($issue->status, ['Resolved', 'Closed', 'Cancelled']))->pluck('title');
        foreach ($project->components as $component) {
            foreach (collect([$component])->merge($component->subcomponents) as $item) {
                if (! in_array($item->lifecycle, ['Completed', 'Cancelled']) && $item->effective_end?->lt(today())) {
                    $reasons->push($item->name.' is delayed');
                }
            }
        }
        foreach ($project->pipelineSteps as $step) {
            if ($project->phase === 'Pipeline' && $step->status !== 'Done' && $step->planned_date?->lt(today())) {
                $reasons->push($step->name.' is delayed');
            }
        }

        return ['project' => $project, 'phase' => $project->phase, 'health' => $reasons->isNotEmpty() ? 'Off track' : 'On track', 'has_issues' => $reasons->isNotEmpty(), 'reasons' => $reasons->unique()->values()->all(), 'demo' => false, 'url' => route('projects.show', $project)];
    }

    public function updates(Request $request): array
    {
        $meetings = PortfolioAccess::scope(Meeting::query())->when(! $request->user()?->hasPortfolioPermission('meetings.view'), fn ($q) => $q->whereRaw('1 = 0'))->latest('scheduled_at')->get()->map(fn (Meeting $meeting): array => ['key' => 'meeting:'.$meeting->id, 'name' => $meeting->name, 'date' => $meeting->scheduled_at, 'location' => $meeting->location ?? 'Not supplied', 'status' => $meeting->status, 'url' => route('meetings.show', $meeting), 'demo' => false, 'order' => $meeting->updated_at->timestamp]);
        $seenComments = $request->user() ? DB::table('comment_reads')->where('user_id', $request->user()->id)->pluck('version', 'comment_id')->all() : $request->session()->get('portfolio_comment_reads', []);
        $comments = PortfolioAccess::scope(Comment::query())->when(! $request->user()?->hasPortfolioPermission('comments.view'), fn ($q) => $q->whereRaw('1 = 0'))->with(['project', 'author'])->latest()->get()->reject(fn (Comment $comment): bool => ($seenComments[$comment->id] ?? 0) === $comment->version || $comment->author_id === $request->user()?->id)->map(fn (Comment $comment): array => ['id' => $comment->id, 'project' => $comment->project->name, 'author' => $comment->author->name, 'body' => $comment->body, 'reply' => $comment->parent_id !== null, 'time' => $comment->created_at->diffForHumans(), 'url' => route('projects.show', $comment->project).'#project-discussion'])->values();

        return ['unseenMeetings' => $meetings->filter(fn (array $meeting): bool => $meeting['status'] === 'Held' && $meeting['date']->lte(now()))->values(), 'upcomingMeetings' => $meetings->filter(fn (array $meeting): bool => $meeting['status'] === 'Scheduled' && $meeting['date']->gte(now()))->sortBy('date')->values(), 'recentComments' => $comments];
    }

    public function markCommentsRead(Request $request, array $ids): void
    {
        $comments = PortfolioAccess::scope(Comment::query())->whereIn('id', $ids)->get();
        foreach ($comments as $comment) {
            if ($request->user()) {
                DB::table('comment_reads')->updateOrInsert(['user_id' => $request->user()->id, 'comment_id' => $comment->id], ['version' => $comment->version, 'read_at' => now()]);
            } else {
                $request->session()->put('portfolio_comment_reads.'.$comment->id, $comment->version);
            }
        }
    }
}
