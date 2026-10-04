<?php

namespace Tests\Feature;

use App\Models\TrackerRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardUpdatesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dashboard.presentation' => false]);
    }

    public function test_dashboard_shows_three_cards_and_issue_bell_instead_of_issue_table(): void
    {
        $response = $this->get(route('dashboard'))->assertOk()->assertSee('Unseen comments')->assertSee('Upcoming meetings')->assertSee('Meeting highlights')->assertDontSee('Issues requiring attention');
        $this->assertSame(3, substr_count($response->getContent(), 'class="portfolio-card"'));
        $response->assertSee('2 projects with issues')->assertSee(e(route('projects.index', ['health' => 'Off track'])), false);
        $this->get(route('projects.index', ['health' => 'Off track']))->assertOk()->assertViewHas('records', fn ($records): bool => $records->total() === 2);
    }

    public function test_meeting_seen_state_is_saved_per_user_and_updated_meetings_reappear(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $meeting = TrackerRecord::factory()->create(['module' => 'meetings', 'data' => ['name' => 'Planning review', 'date' => '2026-10-10T10:00', 'venue' => 'Peshawar', 'agenda' => 'Review allocations', 'minutes' => 'Awaiting approval', 'status' => 'Scheduled']]);
        $this->actingAs($first)->get(route('dashboard'))->assertViewHas('unseenMeetings', fn ($rows): bool => $rows->count() === 1);
        $this->get(route('meetings.show', $meeting))->assertOk()->assertSee('Peshawar')->assertSee('Review allocations')->assertSee('Awaiting approval');
        $this->get(route('dashboard'))->assertViewHas('unseenMeetings', fn ($rows): bool => $rows->isEmpty());
        $this->actingAs($second)->get(route('dashboard'))->assertViewHas('unseenMeetings', fn ($rows): bool => $rows->count() === 1);
        $meeting->update(['data' => array_replace($meeting->data, ['minutes' => 'Approved by participants'])]);
        $this->actingAs($first)->get(route('dashboard'))->assertViewHas('unseenMeetings', fn ($rows): bool => $rows->count() === 1);
        $this->get(route('meetings.show', $meeting))->assertOk();
        $this->assertSame(1, TrackerRecord::where('module', 'meeting_reads')->count());
    }

    public function test_upcoming_meetings_are_scheduled_future_events_sorted_by_date(): void
    {
        $this->travelTo(now('Asia/Karachi')->setDate(2026, 10, 6)->startOfDay());
        foreach ([['Later', '2026-10-20', 'Scheduled'], ['Sooner', '2026-10-07', 'Scheduled'], ['Past', '2026-10-01', 'Held'], ['Cancelled', '2026-10-08', 'Canceled']] as [$name, $date, $status]) {
            TrackerRecord::factory()->create(['module' => 'meetings', 'data' => compact('name', 'date', 'status')]);
        }
        $response = $this->get(route('dashboard'))->assertOk();
        $this->assertSame(['Sooner', 'Later'], $response->viewData('upcomingMeetings')->pluck('name')->all());
    }

    public function test_comments_and_replies_link_to_their_project_and_polling_returns_new_content(): void
    {
        $project = TrackerRecord::factory()->create();
        $data = ['project_key' => 'project:'.$project->id, 'body' => '<script>unsafe()</script>', 'author_name' => 'Aina', 'author_id' => '1', 'context' => 'project', 'parent_id' => null];
        $comment = TrackerRecord::factory()->create(['module' => 'project_comments', 'data' => $data]);
        TrackerRecord::factory()->create(['module' => 'project_comments', 'data' => array_replace($data, ['parent_id' => (string) $comment->id, 'body' => 'New reply'])]);
        $response = $this->get(route('dashboard'))->assertOk()->assertSee('New reply')->assertDontSee('unsafe()', false);
        $this->assertCount(2, $response->viewData('recentComments'));
        $response->assertSee(route('projects.show', $project).'?context=project#project-comments', false);
        TrackerRecord::factory()->create(['module' => 'project_comments', 'data' => array_replace($data, ['body' => 'Latest update'])]);
        $this->getJson(route('dashboard.updates'))->assertOk()->assertJsonStructure(['comments', 'meetings', 'upcoming', 'issues'])->assertSee('New comment')->assertDontSee('Latest update');
    }

    public function test_guest_sample_meeting_is_marked_seen_in_the_session_only(): void
    {
        $this->get(route('dashboard'))->assertViewHas('unseenMeetings', fn ($rows): bool => $rows->count() === 3);
        $this->get(route('meetings.sample-show', 2))->assertOk()->assertSee('Meeting minutes');
        $this->get(route('dashboard'))->assertViewHas('unseenMeetings', fn ($rows): bool => $rows->count() === 2);
        $this->assertDatabaseCount('tracker_records', 0);
    }

    public function test_issue_popup_lists_each_affected_project_and_updates_when_an_issue_is_resolved(): void
    {
        $project = TrackerRecord::factory()->create(['data' => ['name' => 'Project with a blocker', 'stage' => 'Concept']]);
        $issue = TrackerRecord::factory()->create(['module' => 'issues', 'data' => ['name' => 'Pending approval', 'project' => $project->data['name'], 'status' => 'Open']]);
        $this->get(route('dashboard'))->assertOk()->assertSee('data-issue-dropdown', false)
            ->assertSee('class="issue-project-item" href="'.route('projects.overview', $project).'"', false)
            ->assertSee('Project with a blocker')->assertSee('1 projects with issues');
        $issue->update(['data' => array_replace($issue->data, ['status' => 'Resolved'])]);
        $response = $this->getJson(route('dashboard.updates'))->assertOk()->assertJsonPath('issues', 0);
        $this->assertStringContainsString('No projects have open issues.', $response->json('issueProjects'));
        $this->assertStringNotContainsString('Project with a blocker', $response->json('issueProjects'));
    }

    public function test_unseen_comments_exclude_own_posts_and_reads_are_private_to_each_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $project = TrackerRecord::factory()->create();
        $data = ['project_key' => 'project:'.$project->id, 'body' => 'Private message preview', 'author_name' => $other->name, 'author_id' => (string) $other->id, 'context' => 'project'];
        $comments = collect(range(1, 12))->map(fn () => TrackerRecord::factory()->create(['module' => 'project_comments', 'data' => $data]));
        TrackerRecord::factory()->create(['module' => 'project_comments', 'data' => array_replace($data, ['author_id' => (string) $user->id])]);
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('Private message preview');
        $this->assertCount(12, $response->viewData('recentComments'));
        $this->postJson(route('dashboard.comments.read'), ['ids' => $comments->pluck('id')->all()])->assertOk();
        $this->get(route('dashboard'))->assertViewHas('recentComments', fn ($rows): bool => $rows->isEmpty());
        $this->actingAs($other)->get(route('dashboard'))->assertViewHas('recentComments', fn ($rows): bool => $rows->count() === 1);
        $new = TrackerRecord::factory()->create(['module' => 'project_comments', 'data' => array_replace($data, ['parent_id' => (string) $comments->first()->id])]);
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('New reply to a comment');
        $this->assertSame([$new->id], $response->viewData('recentComments')->pluck('id')->all());
        $this->postJson(route('dashboard.comments.read'), ['ids' => ['invalid']])->assertUnprocessable();
    }
}
