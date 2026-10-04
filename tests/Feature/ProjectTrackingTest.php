<?php

namespace Tests\Feature;

use App\Models\TrackerRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectTrackingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dashboard.presentation' => false]);
    }

    public function test_project_chat_updates_include_replies_and_stay_scoped_to_the_project(): void
    {
        $project = TrackerRecord::factory()->create();
        $other = TrackerRecord::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('projects.comments.store', $project), ['body' => 'Stage update', 'context' => 'stage:2'])->assertSessionHasNoErrors();
        $comment = TrackerRecord::where('module', 'project_comments')->sole();
        $this->post(route('projects.comments.store', $project), ['body' => 'Reply update', 'context' => 'stage:2', 'parent_id' => $comment->id])->assertSessionHasNoErrors();
        $reply = TrackerRecord::where('module', 'project_comments')->latest('id')->first();
        $this->post(route('projects.comments.store', $other), ['body' => 'Unrelated discussion', 'context' => 'project'])->assertSessionHasNoErrors();

        $response = $this->getJson(route('projects.show', $project))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(2, 'comments');
        $response->assertJsonPath('comments.0.id', $comment->id)->assertJsonPath('comments.1.id', $reply->id)->assertJsonPath('comments.1.author_id', (string) $user->id);
        $this->assertStringContainsString('Reply update', $response->json('html'));
        $this->assertStringNotContainsString('Unrelated discussion', $response->json('html'));
        $filtered = $this->getJson(route('projects.show', ['record' => $project, 'context' => 'stage:1']))->assertOk()->assertJsonCount(2, 'comments');
        $this->assertStringNotContainsString('Stage update', $filtered->json('html'));
        $this->get(route('projects.show', $project))->assertOk()->assertSee('id="project-chat-toggle"', false)->assertSee('data-project-key="project:'.$project->id.'"', false)->assertSee('aria-expanded="false"', false);
    }

    public function test_example_chat_updates_are_isolated_and_keep_comment_html_escaped(): void
    {
        $this->actingAs(User::factory()->create())->post(route('projects.sample-comments.store', 1), ['body' => '<script>unsafe()</script>', 'context' => 'project'])->assertSessionHasNoErrors();
        $response = $this->getJson(route('projects.sample-show', 1))->assertOk()->assertJsonCount(1, 'comments');
        $this->assertStringContainsString('&lt;script&gt;unsafe()&lt;/script&gt;', $response->json('html'));
        $this->assertStringNotContainsString('<script>unsafe()</script>', $response->json('html'));
        $this->getJson(route('projects.sample-show', 2))->assertOk()->assertJsonCount(0, 'comments');
    }

    public function test_created_project_has_numbered_activities_in_three_stages_and_clickable_tracking_links(): void
    {
        $this->post(route('projects.store'), TrackerRecord::factory()->make()->data)->assertSessionHasNoErrors();
        $project = TrackerRecord::where('module', 'projects')->sole();
        $activities = TrackerRecord::where('module', 'activities')->get();
        $this->assertSame(['Concept' => 5, 'PC-I Development' => 9, 'Implementation' => 7], $activities->countBy('data.stage')->all());
        $this->assertSame(['Activity 1', 'Activity 2', 'Activity 3', 'Activity 4', 'Activity 5'], $activities->where('data.stage', 'Concept')->pluck('data.name')->all());
        $this->get(route('projects.index'))->assertOk()->assertSee('data-project-url="'.route('projects.overview', $project).'"', false);
        $this->get(route('projects.show', $project))->assertOk()->assertSeeInOrder(['Stage 1', 'Stage 2', 'Stage 3'])->assertSee('Activity 9')->assertSee(route('projects.details', $project), false)->assertViewHas('summary', fn (array $summary): bool => $summary['total'] === 21 && $summary['unscheduled'] === 21);
        $this->get(route('projects.details', $project))->assertOk()->assertSee('Implementing agency')->assertSee('Related documents')->assertSee('Key dates');
    }

    public function test_sync_adds_missing_defaults_without_resetting_progress_or_duplicating_records(): void
    {
        $project = TrackerRecord::factory()->create();
        $activity = TrackerRecord::factory()->create(['module' => 'activities', 'data' => ['project_id' => (string) $project->id, 'template_id' => 'concept-note', 'type' => 'Predefined', 'name' => 'Prepare concept note', 'stage' => 'Concept', 'status' => 'Completed', 'remarks' => 'Approved by committee']]);
        $this->artisan('projects:sync-activities')->assertSuccessful();
        $this->artisan('projects:sync-activities')->assertSuccessful();
        $this->assertSame(21, TrackerRecord::where('module', 'activities')->count());
        $this->assertSame('Completed', $activity->fresh()->data['status']);
        $this->assertSame('Approved by committee', $activity->fresh()->data['remarks']);
        $this->assertSame('Activity 1', $activity->fresh()->data['name']);
    }

    public function test_tracking_counts_deadlines_completion_and_unplanned_work_without_other_projects(): void
    {
        $this->travelTo(now('Asia/Karachi')->setDate(2026, 10, 3)->startOfDay());
        $project = TrackerRecord::factory()->create();
        foreach ([['In Progress', '2026-10-02'], ['In Progress', '2026-10-03'], ['Completed', '2026-10-01'], ['Not Started', null], ['On Hold', '2026-11-01'], ['Not Applicable', '2026-10-01']] as [$status, $due]) {
            TrackerRecord::factory()->create(['module' => 'activities', 'data' => ['name' => 'Work item', 'project_id' => (string) $project->id, 'stage' => 'Concept', 'status' => $status, 'due' => $due]]);
        }
        TrackerRecord::factory()->create(['module' => 'activities', 'data' => ['name' => 'Other project activity', 'project_id' => '9999', 'stage' => 'Concept', 'status' => 'In Progress', 'due' => '2026-10-01']]);
        $response = $this->get(route('projects.show', $project))->assertOk()->assertDontSee('Other project activity');
        $this->assertSame(['total' => 6, 'on_track' => 1, 'off_track' => 2, 'completed' => 1, 'unscheduled' => 1], $response->viewData('summary'));
        $this->assertSame(20, $response->viewData('progress'));
    }

    public function test_comments_and_replies_persist_with_real_author_and_inherited_context(): void
    {
        $project = TrackerRecord::factory()->create();
        $user = User::factory()->create(['name' => 'Project Officer']);
        $url = route('projects.comments.store', $project);
        $this->actingAs($user)->post($url, ['body' => 'Please review the schedule.', 'context' => 'stage:2', 'author_name' => 'Forged name'])->assertSessionHasNoErrors()->assertRedirect();
        $comment = TrackerRecord::where('module', 'project_comments')->sole();
        $this->assertSame('Project Officer', $comment->data['author_name']);
        $this->assertSame('project:'.$project->id, $comment->data['project_key']);
        $this->post($url, ['body' => 'Review completed.', 'context' => 'project', 'parent_id' => $comment->id])->assertSessionHasNoErrors();
        $reply = TrackerRecord::where('module', 'project_comments')->latest('id')->first();
        $this->assertSame((string) $comment->id, $reply->data['parent_id']);
        $this->assertSame('stage:2', $reply->data['context']);
        $this->get(route('projects.show', ['record' => $project, 'context' => 'stage:2']))->assertSee('Please review the schedule.')->assertSee('Review completed.');
        $this->get(route('projects.show', ['record' => $project, 'context' => 'stage:1']))->assertDontSee('Please review the schedule.');
    }

    public function test_comments_require_login_validate_input_and_reject_foreign_activity_and_thread(): void
    {
        $project = TrackerRecord::factory()->create();
        $other = TrackerRecord::factory()->create();
        $url = route('projects.comments.store', $project);
        $this->post($url, ['body' => 'No login', 'context' => 'project'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->post($url, ['body' => '', 'context' => 'stage:4'])->assertSessionHasErrors(['body', 'context']);
        $this->post($url, ['body' => str_repeat('a', 3001), 'context' => 'project'])->assertSessionHasErrors('body');
        $foreignActivity = TrackerRecord::factory()->create(['module' => 'activities', 'data' => ['project_id' => (string) $other->id, 'name' => 'Private scope']]);
        $this->post($url, ['body' => 'Wrong activity', 'context' => 'activity:'.$foreignActivity->id])->assertSessionHasErrors('context');
        $this->post(route('projects.comments.store', $other), ['body' => 'Other project comment', 'context' => 'project'])->assertSessionHasNoErrors();
        $foreignComment = TrackerRecord::where('module', 'project_comments')->sole();
        $this->post($url, ['body' => 'Wrong thread', 'context' => 'project', 'parent_id' => $foreignComment->id])->assertSessionHasErrors('parent_id');
        $this->assertSame(1, TrackerRecord::where('module', 'project_comments')->count());
        $this->get(route('projects.show', $project))->assertDontSee('Other project comment');
    }

    public function test_activity_comments_escape_html_and_examples_have_independent_discussions(): void
    {
        $project = TrackerRecord::factory()->create();
        $activity = TrackerRecord::factory()->create(['module' => 'activities', 'data' => ['project_id' => (string) $project->id, 'name' => 'Planning', 'stage' => 'Concept']]);
        $this->actingAs(User::factory()->create())->post(route('projects.comments.store', $project), ['body' => '<script>alert(1)</script>', 'context' => 'activity:'.$activity->id])->assertSessionHasNoErrors();
        $this->get(route('projects.show', $project))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->post(route('projects.sample-comments.store', 1), ['body' => 'Example discussion', 'context' => 'activity:concept-note'])->assertSessionHasNoErrors();
        $this->get(route('projects.sample-show', 1))->assertOk()->assertSee('Example discussion')->assertSee('Activity 9');
        $this->get(route('projects.sample-show', 2))->assertOk()->assertDontSee('Example discussion');
        $this->get(route('projects.sample-details', 1))->assertOk()->assertSee('Health Department')->assertSee('840,000,000.00');
        $this->get('/projects/examples/99')->assertNotFound();
    }

    public function test_details_downloads_and_tracking_reject_other_modules_and_invalid_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('tracker-documents/approval.txt', 'Approval');
        $project = TrackerRecord::factory()->create(['data' => ['name' => 'Document project', 'attachments' => [['title' => 'approval.txt', 'path' => 'tracker-documents/approval.txt', 'uploaded_at' => '2026-10-03']]]]);
        $this->get(route('projects.details', $project))->assertOk()->assertSee('approval.txt')->assertSee('Not specified');
        $url = route('projects.documents.download', [$project, 0]);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($url)->assertDownload('approval.txt');
        $this->get(route('projects.documents.download', [$project, 1]))->assertNotFound();
        $activity = TrackerRecord::factory()->create(['module' => 'activities']);
        $this->get(route('projects.show', $activity))->assertNotFound();
        $this->get(route('projects.details', $activity))->assertNotFound();
        $this->post(route('projects.comments.store', $activity), ['context' => 'project', 'body' => 'Wrong module'])->assertNotFound();
    }
}
