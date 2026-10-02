<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\TrackerRecord;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_uses_static_demo_data_and_filters_it(): void
    {
        TrackerRecord::factory()->create(['data' => ['name' => 'Private live record']]);

        $response = $this->get('/');

        $response->assertOk()->assertSee('Demo data')->assertSee('Recent Updates')->assertDontSee('Private live record');
        $this->assertCount(48, $response->viewData('rows'));
        $this->assertSame(['Concept' => 12, 'PC-I Development' => 16, 'Implementation' => 20], $response->viewData('stageCounts')->all());
        $this->assertSame(['Delayed' => 6, 'On track' => 36, 'On hold' => 6], $response->viewData('healthCounts')->all());
        $filtered = $this->get('/?stage=Concept&health=Delayed');
        $filtered->assertOk();
        $this->assertCount(2, $filtered->viewData('directory'));
    }

    public function test_demo_project_rows_link_to_their_matching_details(): void
    {
        $url = route('dashboard.projects.show', 'IDS-2026-001');
        $this->get('/')->assertSee('Projects requiring attention')->assertSee('data-project-url="'.$url.'"', false);
        $this->get($url)->assertOk()->assertSee('IDS-2026-001')->assertSee('Water Supply Improvement in Khyber District')->assertSee('Stage review overdue')->assertDontSee('Edit project');
        $this->get(route('dashboard.projects.show', 'missing'))->assertNotFound();
    }

    public function test_dashboard_counts_projects_once_and_rolls_up_overdue_work(): void
    {
        Route::get('/', [DashboardController::class, 'live'])->name('dashboard');
        $this->travelTo(now('Asia/Karachi')->setDate(2026, 10, 1)->startOfDay());
        $delayed = TrackerRecord::factory()->create(['data' => ['name' => 'Delayed project', 'reference' => 'IDS-001', 'stage' => 'Concept', 'status' => 'Active']]);
        foreach (['activities', 'subactivities'] as $module) {
            TrackerRecord::factory()->create(['module' => $module, 'data' => ['name' => 'Overdue work', 'project_id' => (string) $delayed->id, 'due' => '2026-09-30', 'status' => 'In Progress', 'stage' => 'Concept']]);
        }
        TrackerRecord::factory()->create(['data' => ['name' => 'Held project', 'stage' => 'Implementation', 'status' => 'On hold', 'completion' => '2026-09-01']]);
        TrackerRecord::factory()->create(['data' => ['name' => 'On time', 'stage' => 'Concept', 'status' => 'Active', 'completion' => '2026-10-01']]);
        TrackerRecord::factory()->create(['data' => ['name' => 'Finished', 'stage' => 'Implementation', 'status' => 'Completed', 'completion' => '2026-09-01']]);
        TrackerRecord::factory()->create(['data' => ['name' => 'Undated', 'stage' => 'Concept', 'status' => 'Active']]);

        $response = $this->get('/');

        $response->assertOk()->assertDontSee('data-group="issues"', false);
        $this->assertSame(['Delayed' => 1, 'On track' => 1, 'On hold' => 1], $response->viewData('healthCounts')->all());
        $this->assertSame(3, $response->viewData('stageCounts')['Concept']);
        $this->assertSame('Schedule not set', $response->viewData('rows')->firstWhere('project.data.name', 'Undated')['health']);
        $this->get('/issues')->assertOk();
    }

    public function test_dashboard_filters_cards_and_lists_by_multivalue_project_fields(): void
    {
        Route::get('/', [DashboardController::class, 'live'])->name('dashboard');
        TrackerRecord::factory()->create(['data' => ['name' => 'Matching project', 'stage' => 'Concept', 'status' => 'On hold', 'partners' => ['ADB', 'World Bank'], 'districts' => ['Kohat', 'Swat'], 'sectors' => ['Health']]]);
        TrackerRecord::factory()->create(['data' => ['name' => 'Unrelated', 'stage' => 'Implementation', 'status' => 'Active']]);

        $response = $this->get('/?partner=World%20Bank&district=Swat&sector=Health&health=On%20hold');

        $response->assertOk()->assertSee('Matching project')->assertDontSee('Unrelated');
        $this->assertCount(1, $response->viewData('directory'));
        $this->assertSame(1, $response->viewData('healthCounts')['On hold']);
    }

    public function test_completed_work_is_not_overdue_and_subactivity_alone_can_delay_project(): void
    {
        Route::get('/', [DashboardController::class, 'live'])->name('dashboard');
        $project = TrackerRecord::factory()->create();
        TrackerRecord::factory()->create(['module' => 'activities', 'data' => ['project_id' => (string) $project->id, 'name' => 'Finished task', 'due' => '2020-01-01', 'status' => 'Completed']]);
        $response = $this->get('/');
        $this->assertSame(0, $response->viewData('healthCounts')['Delayed']);

        TrackerRecord::factory()->create(['module' => 'subactivities', 'data' => ['project_id' => (string) $project->id, 'name' => 'Late child', 'due' => '2020-01-01', 'status' => 'Not Started']]);
        $response = $this->get('/');
        $response->assertOk()->assertSee('Projects requiring attention');
        $this->assertSame(['Sub-activity: Late child (Concept)'], $response->viewData('rows')->first()['reasons']);
        $this->assertSame(1, $response->viewData('healthCounts')['Delayed']);
    }
}
