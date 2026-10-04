<?php

namespace Tests\Feature;

use App\Models\TrackerRecord;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dashboard.presentation' => false]);
    }

    public function test_dashboard_uses_demo_data_until_live_projects_are_available(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertOk()->assertSee('Demo data')->assertDontSee('data-dashboard-filters', false);
        $this->assertCount(10, $response->viewData('rows'));
        $response->assertViewIs('dashboard')->assertSee('Flagship projects');
        $this->assertCount(3, $response->viewData('flagshipProjects'));
        foreach ($response->viewData('flagshipProjects') as $row) {
            $this->assertTrue($row['project']->data['is_flagship']);
            $response->assertSee($row['url'], false);
        }
        $this->assertSame([10, 5, 5, 2, 8], array_column($response->viewData('cards'), 'count'));
        $filtered = $this->get(route('projects.index', ['phase' => 'Pipeline', 'health' => 'Off track']));
        $filtered->assertOk();
        $this->assertSame(1, $filtered->viewData('records')->total());
        TrackerRecord::factory()->create(['data' => ['name' => 'Live record']]);
        $response = $this->get(route('dashboard'))->assertOk()->assertSee('Live project portfolio');
        $this->assertCount(1, $response->viewData('rows'));
    }

    public function test_demo_project_rows_link_to_their_matching_details(): void
    {
        $url = route('dashboard.projects.show', 'IDS-2026-001');
        $this->get(route('dashboard'))->assertSee('data-issue-notification', false);
        $this->get(route('projects.index', ['health' => 'Off track']))->assertSee('data-project-url="'.$url.'"', false);
        $this->get($url)->assertOk()->assertSee('IDS-2026-001')->assertSee('Water Supply Improvement in Khyber District')->assertSee('Pipeline component 3 is delayed')->assertDontSee('Edit project');
        $this->get(route('dashboard.projects.show', 'missing'))->assertNotFound();
    }

    public function test_dashboard_counts_projects_once_and_rolls_up_overdue_work(): void
    {
        $this->travelTo(now('Asia/Karachi')->setDate(2026, 10, 1)->startOfDay());
        $delayed = TrackerRecord::factory()->create(['data' => ['name' => 'Delayed project', 'reference' => 'IDS-001', 'stage' => 'Concept', 'status' => 'Active']]);
        foreach (['activities', 'subactivities'] as $module) {
            TrackerRecord::factory()->create(['module' => $module, 'data' => ['name' => 'Overdue work', 'project_id' => (string) $delayed->id, 'due' => '2026-09-30', 'status' => 'In Progress', 'stage' => 'Concept']]);
        }
        TrackerRecord::factory()->create(['data' => ['name' => 'Held project', 'stage' => 'Implementation', 'status' => 'On hold', 'completion' => '2026-09-01']]);
        TrackerRecord::factory()->create(['data' => ['name' => 'On time', 'stage' => 'Concept', 'status' => 'Active', 'completion' => '2026-10-01']]);
        TrackerRecord::factory()->create(['data' => ['name' => 'Finished', 'stage' => 'Implementation', 'status' => 'Completed', 'completion' => '2026-09-01']]);
        TrackerRecord::factory()->create(['data' => ['name' => 'Undated', 'stage' => 'Concept', 'status' => 'Active']]);

        $response = $this->get(route('dashboard'));

        $response->assertOk()->assertDontSee('data-group="issues"', false);
        $this->assertSame([5, 3, 2, 1, 3], array_column($response->viewData('cards'), 'count'));
        $this->assertSame(3, $response->viewData('rows')->where('phase', 'Pipeline')->count());
        $this->assertSame('On track', $response->viewData('rows')->firstWhere('project.data.name', 'Undated')['health']);
        $this->get('/issues')->assertOk();
    }

    public function test_dashboard_filters_cards_and_lists_by_multivalue_project_fields(): void
    {
        TrackerRecord::factory()->create(['data' => ['name' => 'Matching project', 'stage' => 'Concept', 'status' => 'On hold', 'partners' => ['ADB', 'World Bank'], 'districts' => ['Kohat', 'Swat'], 'sectors' => ['Health']]]);
        TrackerRecord::factory()->create(['data' => ['name' => 'Unrelated', 'stage' => 'Implementation', 'status' => 'Active']]);

        $response = $this->get('/projects?partner=World%20Bank&district=Swat&sector=Health&health=On%20track');

        $response->assertOk()->assertSee('Matching project')->assertDontSee('Unrelated');
        $this->assertCount(1, $response->viewData('records')->items());
        $this->assertSame('On track', $response->viewData('records')->first()['health']);
    }

    public function test_completed_work_is_not_overdue_and_subactivity_alone_can_delay_project(): void
    {
        $project = TrackerRecord::factory()->create();
        TrackerRecord::factory()->create(['module' => 'activities', 'data' => ['project_id' => (string) $project->id, 'name' => 'Finished task', 'due' => '2020-01-01', 'status' => 'Completed']]);
        $response = $this->get(route('dashboard'));
        $this->assertSame(0, $response->viewData('rows')->where('has_issues', true)->count());

        TrackerRecord::factory()->create(['module' => 'subactivities', 'data' => ['project_id' => (string) $project->id, 'name' => 'Late child', 'due' => '2020-01-01', 'status' => 'Not Started']]);
        $response = $this->get(route('dashboard'));
        $response->assertOk()->assertSee('data-issue-notification', false);
        $this->assertSame(['Sub-component: Late child is delayed'], $response->viewData('rows')->first()['reasons']);
        $this->assertSame(1, $response->viewData('rows')->where('has_issues', true)->count());
    }
}
