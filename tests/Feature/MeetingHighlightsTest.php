<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeetingHighlightsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portfolio.enabled' => true]);
        Route::setRoutes(new RouteCollection);
        Route::middleware('web')->group(base_path('routes/web.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    private function signIn(string $role = 'Super Admin'): User
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create(['role_id' => Role::where('name', $role)->sole()->id, 'account_status' => 'Active']);
        $this->actingAs($user);

        return $user;
    }

    public function test_highlights_show_only_held_meetings_and_upcoming_shows_only_future_scheduled_meetings(): void
    {
        $this->signIn();
        foreach ([['Held review', -1, 'Held'], ['Next review', 1, 'Scheduled'], ['Overdue schedule', -2, 'Scheduled'], ['Cancelled review', 2, 'Cancelled'], ['Future held', 3, 'Held']] as [$name, $days, $status]) {
            Meeting::create(['name' => $name, 'scheduled_at' => now()->addDays($days), 'status' => $status, 'version' => 1]);
        }
        $response = $this->get('/dashboard')->assertOk();
        $this->assertSame(['Held review'], $response->viewData('unseenMeetings')->pluck('name')->all());
        $this->assertSame(['Next review'], $response->viewData('upcomingMeetings')->pluck('name')->all());
        $this->get(route('meetings.show', Meeting::where('name', 'Held review')->sole()))->assertOk();
        $this->get('/dashboard')->assertViewHas('unseenMeetings', fn ($meetings): bool => $meetings->pluck('name')->all() === ['Held review']);
        $updates = $this->getJson('/dashboard/updates')->assertOk();
        $this->assertStringContainsString('Held review', $updates->json('meetings'));
        $this->assertStringNotContainsString('Next review', $updates->json('meetings'));
    }

    public function test_held_meeting_minutes_can_be_uploaded_downloaded_and_retained_on_edit(): void
    {
        Storage::fake('local');
        $this->signIn();
        $data = ['name' => 'Held review', 'scheduled_at' => now()->subDay()->toDateTimeString(), 'status' => 'Held'];
        $this->post('/meetings', $data + ['minutes_document' => UploadedFile::fake()->create('minutes.pdf', 25, 'application/pdf')])->assertSessionHasNoErrors()->assertRedirect();
        $meeting = Meeting::sole();
        Storage::disk('local')->assertExists($meeting->minutes_path);
        $this->get(route('meetings.minutes', $meeting))->assertDownload('minutes.pdf');
        $this->put(route('meetings.update', $meeting), $data + ['minutes' => 'Approved minutes'])->assertSessionHasNoErrors();
        $this->assertSame($meeting->minutes_path, $meeting->fresh()->minutes_path);
        $this->get(route('meetings.show', $meeting))->assertSee('Download minutes document');

        $this->actingAs(User::factory()->create(['account_status' => 'Active']));
        $this->get(route('meetings.minutes', $meeting))->assertForbidden();
        $this->put(route('meetings.update', $meeting), $data)->assertForbidden();
        $this->signIn('Project Director');
        $this->get(route('meetings.minutes', $meeting))->assertNotFound();
    }

    public function test_minutes_reject_invalid_files_and_meetings_that_have_not_been_held(): void
    {
        Storage::fake('local');
        $this->signIn();
        $data = ['name' => 'Review', 'scheduled_at' => now()->subDay()->toDateTimeString(), 'status' => 'Held'];
        $this->post('/meetings', $data + ['minutes_document' => UploadedFile::fake()->create('script.php', 2, 'text/plain')])->assertSessionHasErrors('minutes_document');
        $this->post('/meetings', array_replace($data, ['status' => 'Scheduled', 'minutes_document' => UploadedFile::fake()->create('minutes.pdf', 25, 'application/pdf')]))->assertSessionHasErrors('minutes_document');
        $this->post('/meetings', array_replace($data, ['scheduled_at' => now()->addDay()->toDateTimeString(), 'minutes_document' => UploadedFile::fake()->create('minutes.pdf', 25, 'application/pdf')]))->assertSessionHasErrors('minutes_document');
        $this->post('/meetings', $data + ['minutes_document' => UploadedFile::fake()->create('large.pdf', 20481, 'application/pdf')])->assertSessionHasErrors('minutes_document');
        $this->assertDatabaseCount('meetings', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_navigation_is_simplified_and_meeting_actions_are_removed(): void
    {
        $this->signIn();
        $response = $this->get('/dashboard')->assertOk()->assertSee('Manage Pipeline steps')->assertSee('Add Pipeline step')->assertSee('Manage financial information')->assertDontSee('Manage Contacts')->assertDontSee('Reporting periods')->assertDontSee('Add District')->assertDontSee('Meeting actions')->assertDontSee('Pipeline step templates');
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'data-group="sectors"'), strpos($html, 'Manage financial information'));
        $this->get('/districts')->assertOk()->assertDontSee('href="'.route('districts.create').'"', false);
        $this->get('/pipeline-templates')->assertOk()->assertSee('Add pipeline step')->assertDontSee('Component phase')->assertDontSee('>Archive<', false);
        $this->get('/meeting-actions')->assertNotFound();
        $this->assertFalse(Schema::hasTable('meeting_actions'));

        $this->assertDatabaseMissing('permissions', ['code' => 'meeting-actions.view']);
    }
}
