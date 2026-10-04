<?php

namespace Tests\Feature;

use App\Models\TrackerRecord;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrackerPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dashboard.presentation' => false]);
    }

    public static function modules(): array
    {
        return array_map(fn (string $module): array => [$module], ['projects', 'activities', 'issues', 'meetings', 'reports', 'users', 'references', 'roles']);
    }

    public static function managedModules(): array
    {
        return [...self::modules(), ['sectors'], ['partners']];
    }

    #[DataProvider('managedModules')]
    public function test_modules_have_separate_pages_and_persist_records(string $module): void
    {
        $values = [];
        foreach (config("tracker.{$module}.fields") as $field) {
            $values[$field[0]] = match ($field[2]) {
                'select' => $field[4][0] ?? '',
                'multiselect' => [$field[4][0]],
                'number' => '1000.00',
                'url' => 'https://example.org/meeting',
                'date' => '2026-10-01',
                'datetime-local' => '2026-10-01T10:00',
                'email' => 'officer@example.org',
                default => 'Test record',
            };
        }
        if ($module === 'activities') {
            $project = TrackerRecord::factory()->create();
            $values['project_id'] = (string) $project->id;
        }
        if ($module === 'projects') {
            $values['currency'] = 'PKR';
        }
        if ($module === 'users') {
            TrackerRecord::factory()->create(['module' => 'roles', 'data' => ['name' => $values['role'], 'permissions' => []]]);
        }
        $this->get("/{$module}/create")->assertOk()->assertViewIs("{$module}.create");
        $response = $this->post("/{$module}", $values);
        $response->assertSessionHasNoErrors();
        $record = TrackerRecord::where('module', $module)->sole();
        $response->assertRedirect(route("{$module}.show", $record));
        $this->assertSame('Test record', $record->data['name']);
        $this->get("/{$module}")->assertSee('Test record')->assertViewIs($module === 'projects' ? 'projects.portfolio-index' : "{$module}.index");
        $this->get("/{$module}/{$record->id}")->assertSee('Test record')->assertViewIs("{$module}.show");
        $this->get("/{$module}/{$record->id}/edit")->assertOk()->assertViewIs("{$module}.edit");
        $values['name'] = 'Updated record';
        $this->put("/{$module}/{$record->id}", $values)->assertRedirect(route("{$module}.show", $record));
        $this->assertSame('Updated record', $record->fresh()->data['name']);
        if ($module === 'projects') {
            $this->delete("/projects/{$record->id}")->assertStatus(405);
            $this->assertModelExists($record);
        } else {
            $this->get("/{$module}/{$record->id}/delete")->assertOk()->assertSee('This action cannot be undone.');
            $this->delete("/{$module}/{$record->id}")->assertRedirect(route("{$module}.index"));
            $this->assertModelMissing($record);
        }
    }

    public function test_sector_name_is_required_and_other_fields_are_not_saved(): void
    {
        $this->post('/sectors', [])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('tracker_records', 0);

        $this->post('/sectors', ['name' => 'Renewable energy', 'status' => 'Active'])->assertSessionHasNoErrors();

        $this->assertSame(['name' => 'Renewable energy'], TrackerRecord::where('module', 'sectors')->sole()->data);
        $this->get('/projects/create')->assertSee('Renewable energy');
    }

    public function test_partner_name_is_required_and_saved_partners_are_project_options(): void
    {
        $this->post('/partners', [])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('tracker_records', 0);

        $this->post('/partners', ['name' => 'New donor', 'status' => 'Active'])->assertSessionHasNoErrors();

        $this->assertSame(['name' => 'New donor'], TrackerRecord::where('module', 'partners')->sole()->data);
        $this->get('/projects/create')->assertSee('New donor')->assertDontSee('Reference Lists');
    }

    public function test_kp_districts_are_available_in_creation_and_chart_filters_with_a_simple_project_list(): void
    {
        foreach (['/projects/create'] as $page) {
            $this->get($page)->assertSee('Abbottabad')->assertSee('Upper Chitral')
                ->assertSee('Paharpur')->assertSee('Upper Swat')->assertSee('Bajaur')
                ->assertSee('Merged districts')->assertDontSee('F.A.T.A');
        }

        $values = TrackerRecord::factory()->make()->data;
        $this->get('/projects')->assertDontSee('<select class="field mt-2" name="district">', false);
        $values['districts'] = ['Abbottabad', 'Bajaur', 'Merged districts'];
        $this->post('/projects', $values)->assertSessionHasNoErrors();
        $this->assertSame($values['districts'], TrackerRecord::where('module', 'projects')->sole()->data['districts']);
        $this->get('/projects?district=Abbottabad')->assertOk()->assertSee($values['name']);
        $this->get('/projects?district=Upper+Chitral')->assertOk()->assertDontSee($values['name']);
    }

    public function test_sector_list_filters_and_escapes_names(): void
    {
        TrackerRecord::factory()->create(['module' => 'sectors', 'data' => ['name' => '<script>alert(1)</script>']]);
        TrackerRecord::factory()->create(['module' => 'sectors', 'data' => ['name' => 'Unrelated sector']]);

        $this->get('/sectors?search=alert')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('Unrelated sector');
    }

    public function test_project_edit_keeps_existing_activities_on_their_separate_pages(): void
    {
        $this->post('/projects', TrackerRecord::factory()->make()->data)->assertSessionHasNoErrors();
        $project = TrackerRecord::where('module', 'projects')->sole();
        $activity = TrackerRecord::where('module', 'activities')->first();
        $this->put(route('activities.update', $activity), array_replace($activity->data, ['status' => 'Completed']))->assertSessionHasNoErrors();

        $this->put(route('projects.update', $project), array_replace($project->data, ['name' => 'Updated project']))->assertSessionHasNoErrors();

        $this->assertSame('Completed', $activity->fresh()->data['status']);
        $this->assertSame('Updated project', $activity->fresh()->data['project']);
        $this->get(route('projects.edit', $project))->assertDontSee('id="project-activities"', false)->assertDontSee('name="parent_id"', false);
        $this->get(route('activities.show', $activity))->assertSee('Completed');
    }

    public function test_invalid_stage_and_nested_activity_input_are_rejected_without_saving(): void
    {
        $values = TrackerRecord::factory()->make()->data;
        $values['stage'] = 'Invalid';
        $values['activities'] = [['name' => '', 'stage' => 'Invalid']];

        $this->post('/projects', $values)->assertSessionHasErrors(['stage', 'activities']);

        $this->assertDatabaseCount('tracker_records', 0);
    }

    public function test_required_project_fields_are_validated(): void
    {
        $this->post('/projects', [])->assertSessionHasErrors(['name', 'reference', 'stage', 'status', 'agency']);
        $this->assertDatabaseCount('tracker_records', 0);
    }

    public function test_record_cannot_be_accessed_or_deleted_through_another_module(): void
    {
        $record = TrackerRecord::factory()->create();

        $this->get("/issues/{$record->id}")->assertNotFound();
        $this->get("/issues/{$record->id}/edit")->assertNotFound();
        $this->delete("/issues/{$record->id}")->assertNotFound();

        $this->assertModelExists($record);
    }

    #[DataProvider('modules')]
    public function test_static_blade_examples_have_working_detail_and_edit_pages(string $module): void
    {
        $this->get("/{$module}")->assertOk()->assertSee($module === 'projects' ? 'Demo' : 'Example');
        foreach ([1, 2, 3] as $sample) {
            $this->get("/{$module}/examples/{$sample}")->assertOk()->assertSee('Example record');
            $this->get("/{$module}/examples/{$sample}/edit")->assertOk()->assertSee('Save');
        }
        $this->assertDatabaseCount('tracker_records', 0);
    }

    public function test_stage_changes_require_a_reason_without_a_date(): void
    {
        $record = TrackerRecord::factory()->create();
        $values = array_merge($record->data, ['stage' => 'Implementation']);

        $this->put("/projects/{$record->id}", $values)->assertSessionHasErrors(['stage_change_reason'])->assertSessionDoesntHaveErrors('stage_changed_at');
        $this->assertSame('Concept', $record->fresh()->data['stage']);

        $this->put("/projects/{$record->id}", array_replace($values, ['stage_change_reason' => 'Approved for implementation']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Implementation', $record->fresh()->data['stage']);
        $this->get('/projects/create')->assertOk()->assertDontSee('type="date"', false);
        $this->get("/projects/{$record->id}/edit")->assertOk()->assertDontSee('type="date"', false);
    }

    public function test_duplicate_reference_and_reversed_activity_dates_are_rejected(): void
    {
        $record = TrackerRecord::factory()->create();
        $this->post('/projects', $record->data)->assertSessionHasErrors('reference');
        $values = TrackerRecord::factory()->make()->data;
        $values = ['type' => 'Custom', 'project_id' => (string) $record->id, 'name' => 'Survey', 'stage' => 'Concept', 'status' => 'In Progress', 'start' => '2026-10-20', 'due' => '2026-10-01'];
        $this->post('/activities', $values)->assertSessionHasErrors('due');
        $this->assertDatabaseCount('tracker_records', 1);
    }

    public function test_project_documents_are_saved_privately_and_retained_on_edit(): void
    {
        Storage::fake('local');
        $values = TrackerRecord::factory()->make()->data;
        $values['documents'] = [UploadedFile::fake()->create('Concept note.pdf', 20, 'application/pdf')];

        $this->post('/projects', $values)->assertSessionHasNoErrors();

        $record = TrackerRecord::where('module', 'projects')->sole();
        $attachment = $record->data['attachments'][0];
        Storage::disk('local')->assertExists($attachment['path']);
        $this->assertSame('Concept note.pdf', $attachment['title']);
        unset($values['documents']);
        $this->put("/projects/{$record->id}", $values)->assertSessionHasNoErrors();
        $this->assertSame($attachment, $record->fresh()->data['attachments'][0]);
    }

    public function test_unsupported_document_upload_does_not_create_a_project(): void
    {
        Storage::fake('local');
        $values = TrackerRecord::factory()->make()->data;
        $values['documents'] = [UploadedFile::fake()->create('script.php', 1, 'application/x-httpd-php')];

        $this->post('/projects', $values)->assertSessionHasErrors('documents.0');

        $this->assertDatabaseCount('tracker_records', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_meetings_save_multiple_projects_and_follow_up_actions(): void
    {
        $values = [
            'name' => 'Joint review', 'projects' => ['Rural Health Centres (Phase I)', 'Secondary Schools Upgradation'],
            'date' => '2026-10-05T10:00', 'status' => 'Scheduled',
            'actions' => [['description' => 'Send cost estimates', 'officer' => 'Section Officer', 'due' => '2026-10-10', 'status' => 'Not Started', 'activity' => 'Prepare PC-I']],
        ];

        $this->post('/meetings', $values)->assertSessionHasNoErrors();

        $record = TrackerRecord::sole();
        $this->assertSame($values['projects'], $record->data['projects']);
        $this->assertSame('Send cost estimates', $record->data['actions'][0]['description']);
        $this->get("/meetings/{$record->id}")->assertSee('Send cost estimates');
    }

    public function test_list_search_filters_records_and_details_escape_user_content(): void
    {
        $record = TrackerRecord::factory()->create(['data' => ['name' => '<script>alert(1)</script>']]);
        TrackerRecord::factory()->create(['data' => ['name' => 'Unrelated project']]);

        $this->get('/projects?search=alert')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('Unrelated project');
        $this->get("/projects/{$record->id}")->assertDontSee('<script>alert(1)</script>', false);
    }
}
