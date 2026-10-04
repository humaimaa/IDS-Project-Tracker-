<?php

namespace Tests\Feature;

use App\Models\TrackerRecord;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProjectPortfolioTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dashboard.presentation' => false]);
    }

    public function test_dashboard_cards_and_chart_links_use_the_same_filtered_portfolio(): void
    {
        $matching = TrackerRecord::factory()->create(['data' => ['name' => 'Matching project', 'reference' => 'MATCH', 'stage' => 'Implementation', 'status' => 'Active', 'partners' => ['ADB', 'World Bank'], 'districts' => ['Swat'], 'sectors' => ['Health']]]);
        TrackerRecord::factory()->create(['data' => ['name' => 'Other project', 'stage' => 'Concept', 'status' => 'On hold', 'partners' => ['Other'], 'districts' => ['Kohat'], 'sectors' => ['Education']]]);
        $dashboard = $this->get(route('dashboard'))->assertOk()->assertDontSee('data-dashboard-filters', false);
        $this->assertSame([2, 1, 1, 0, 2], array_column($dashboard->viewData('cards'), 'count'));
        foreach (['partner' => 'World Bank', 'sector' => 'Health', 'district' => 'Swat', 'phase' => 'Ongoing', 'health' => 'On track'] as $key => $value) {
            $url = route('projects.index', $key === 'health' ? [$key => $value, 'partner' => 'World Bank'] : [$key => $value]);
            if ($key !== 'health') {
                $dashboard->assertSee(e($url), false);
            }
            $list = $this->get($url)->assertOk()->assertSee('Matching project');
            $this->assertSame(1, $list->viewData('records')->total());
            $this->assertSame($matching->id, $list->viewData('records')->items()[0]['project']->id);
        }
        $this->get(route('projects.index', ['partner' => 'Missing']))->assertSee('No projects match this selection.');
    }

    public function test_delays_and_unresolved_custom_issues_count_projects_once_and_clear_when_resolved(): void
    {
        $this->travelTo(now('Asia/Karachi')->setDate(2026, 10, 6)->startOfDay());
        $project = TrackerRecord::factory()->create(['data' => ['name' => 'At risk', 'stage' => 'Implementation', 'status' => 'On hold']]);
        $component = TrackerRecord::factory()->create(['module' => 'activities', 'data' => ['project_id' => (string) $project->id, 'name' => 'Late component', 'status' => 'In Progress', 'due' => '2026-10-05']]);
        $sub = TrackerRecord::factory()->create(['module' => 'subactivities', 'data' => ['project_id' => (string) $project->id, 'name' => 'Late sub-component', 'status' => 'In Progress', 'due' => '2026-10-04']]);
        $issue = TrackerRecord::factory()->create(['module' => 'issues', 'data' => ['project' => 'At risk', 'name' => 'Land unavailable', 'status' => 'Open']]);
        TrackerRecord::factory()->create(['module' => 'issues', 'data' => ['project' => 'Another project', 'name' => 'Unrelated issue', 'status' => 'Open']]);
        $response = $this->get(route('dashboard'))->assertOk();
        $this->get(route('projects.overview', $project))->assertOk()->assertSee('Land unavailable')->assertSee('Late sub-component')->assertDontSee('Unrelated issue');
        $this->assertSame([1, 0, 1, 1, 0], array_column($response->viewData('cards'), 'count'));
        $this->assertCount(3, $response->viewData('rows')->first()['reasons']);
        foreach ([$component, $sub] as $item) {
            $item->update(['data' => array_replace($item->data, ['status' => 'Completed'])]);
        }
        $issue->update(['data' => array_replace($issue->data, ['status' => 'Resolved'])]);
        $response = $this->get(route('dashboard'))->assertOk();
        $this->assertSame(0, $response->viewData('cards')[3]['count']);
    }

    public function test_financial_year_and_quarter_selection_changes_the_figures_and_rejects_invalid_values(): void
    {
        $url = route('dashboard.projects.show', 'IDS-2026-002');
        $first = $this->get($url.'?tab=financial&year=2024-25&quarter=1')->assertOk()->assertSee('Illustrative financial data');
        $second = $this->get($url.'?tab=financial&year=2026-27&quarter=2')->assertOk();
        $this->assertSame([1], array_keys($first->viewData('quarters')));
        $this->assertSame([2], array_keys($second->viewData('quarters')));
        $this->assertNotSame($first->viewData('annual')['achieved'], $second->viewData('annual')['achieved']);
        $this->assertNotSame($first->viewData('quarters')[1]['release'], $second->viewData('quarters')[2]['release']);
        $finance = $first->viewData('finance');
        $this->assertEqualsWithDelta($finance['commitment'] - $finance['disbursed'], $finance['undisbursed'], 0.01);
        $this->getJson($url.'?tab=financial&year=2000&quarter=9')->assertUnprocessable()->assertJsonValidationErrors(['year', 'quarter']);
    }

    public function test_project_overview_has_five_pipeline_and_six_physical_components_with_varied_children(): void
    {
        $url = route('dashboard.projects.show', 'IDS-2026-001');
        $this->get($url)->assertOk()->assertSee('General information')->assertDontSee('Financial information')->assertSee('Pipeline components')->assertDontSee('Physical progress');
        $this->get($url.'?tab=financial')->assertOk()->assertViewHas('tab', 'general')->assertViewHas('finance', [])->assertDontSee('Illustrative financial data');
        $pipeline = $this->get($url.'?tab=pipeline')->assertOk()->assertSee('aria-current="step"', false);
        $this->assertCount(5, $pipeline->viewData('components'));
        $this->assertTrue($pipeline->viewData('components')->every(fn (array $component): bool => $component['subcomponents'] === []));
        $this->assertCount(1, $pipeline->viewData('components')->where('current', true));
        $this->get($url.'?tab=physical')->assertViewHas('tab', 'pipeline');
        $ongoingUrl = route('dashboard.projects.show', 'IDS-2026-002');
        $this->get($ongoingUrl)->assertOk()->assertSee('Physical progress')->assertDontSee('Pipeline components');
        $this->get($ongoingUrl.'?tab=pipeline')->assertViewHas('tab', 'physical');
        $physical = $this->get($ongoingUrl.'?tab=physical')->assertOk()->assertSee('Sub-component 6.');
        $components = $physical->viewData('components');
        $this->assertCount(6, $components);
        $counts = $components->map(fn (array $component): int => count($component['subcomponents']));
        $this->assertGreaterThan(1, $counts->unique()->count());
        $this->assertTrue($counts->every(fn (int $count): bool => $count >= 2 && $count <= 5));
    }

    public function test_live_details_are_scoped_escape_text_and_keep_zero_cost_charts_valid(): void
    {
        $project = TrackerRecord::factory()->create(['data' => ['name' => '<script>bad()</script>', 'cost' => '0', 'stage' => 'Implementation']]);
        $url = route('projects.overview', $project);
        $this->get($url)->assertOk()->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false);
        $response = $this->get($url.'?tab=financial')->assertOk()->assertDontSee('NAN')->assertDontSee('INF');
        $this->assertSame(0.0, $response->viewData('finance')['disbursed']);
        $issue = TrackerRecord::factory()->create(['module' => 'issues']);
        $this->get(route('projects.overview', $issue))->assertNotFound();
        $this->get(route('dashboard.projects.show', 'missing'))->assertNotFound();
    }

    public function test_project_list_pagination_keeps_filters_and_demo_counts_match(): void
    {
        $dashboard = $this->get(route('dashboard'))->assertOk();
        $this->assertSame([10, 5, 5, 2, 8], array_column($dashboard->viewData('cards'), 'count'));
        $list = $this->get(route('projects.index', ['partner' => 'ADB']))->assertOk();
        $this->assertSame(4, $list->viewData('records')->total());
        $this->assertCount(4, $list->viewData('records')->items());
        $this->assertStringContainsString('partner=ADB', $list->viewData('records')->url(1));
        $this->get(route('issues.create', ['project' => 'Water Supply Improvement in Khyber District']))->assertOk()->assertSee('Water Supply Improvement in Khyber District');
    }

    public function test_raising_and_resolving_an_issue_updates_the_portfolio(): void
    {
        $project = TrackerRecord::factory()->create(['data' => ['name' => 'Project needing approval', 'stage' => 'Concept', 'status' => 'Active']]);
        $values = ['name' => 'Approval blocked', 'project' => $project->data['name'], 'stage' => 'Concept', 'reported_at' => '2026-10-06', 'reported_by' => 'Project director', 'priority' => 'High', 'officer' => 'Planning department', 'status' => 'Open'];
        $this->post(route('issues.store'), $values)->assertSessionHasNoErrors();
        $issue = TrackerRecord::where('module', 'issues')->sole();
        $this->assertSame('Approval blocked', $issue->data['name']);
        $this->get(route('projects.index', ['health' => 'Off track']))->assertSee($project->data['name']);
        $this->put(route('issues.update', $issue), array_replace($values, ['status' => 'Resolved']))->assertSessionHasNoErrors();
        $this->assertSame('Resolved', $issue->fresh()->data['status']);
        $this->get(route('projects.index', ['health' => 'Off track']))->assertSee('No projects match this selection.');
    }

    public function test_empty_financial_filters_default_and_explicit_delays_are_detected(): void
    {
        $project = TrackerRecord::factory()->create(['data' => ['name' => 'Ongoing project', 'stage' => 'Implementation']]);
        TrackerRecord::factory()->create(['module' => 'activities', 'data' => ['project_id' => (string) $project->id, 'name' => 'Explicitly delayed', 'status' => 'Delayed']]);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('rows', fn ($rows): bool => $rows->first()['has_issues']);
        $this->get(route('projects.overview', $project))->assertSee('Explicitly delayed');
        $response = $this->get(route('projects.overview', $project).'?tab=financial&year=&quarter=')->assertOk();
        $this->assertSame('2026-27', $response->viewData('year'));
        $this->assertCount(4, $response->viewData('quarters'));
    }

    public function test_project_actions_separate_unresolved_issues_and_keep_history_scoped(): void
    {
        $project = TrackerRecord::factory()->create(['data' => ['name' => 'Issue panel project']]);
        foreach (['Open', 'Resolved'] as $status) {
            TrackerRecord::factory()->create(['module' => 'issues', 'data' => ['name' => $status.' issue', 'project' => 'Issue panel project', 'status' => $status]]);
        }
        TrackerRecord::factory()->create(['module' => 'issues', 'data' => ['name' => 'Unrelated issue', 'project' => 'Another project', 'status' => 'Open']]);
        $response = $this->get(route('projects.overview', $project))->assertOk()->assertSee('Add issue')->assertSee('View all project issues (2)')->assertSee('Resolved issue')->assertDontSee('Unrelated issue');
        $this->assertCount(1, $response->viewData('openIssues'));
        $this->assertSame('Open issue', $response->viewData('openIssues')->first()['name']);
    }

    public function test_presentation_uses_ten_consistent_projects_even_with_saved_records(): void
    {
        config(['dashboard.presentation' => true]);
        TrackerRecord::factory()->create(['data' => ['name' => 'Hidden live record']]);
        $dashboard = $this->get(route('dashboard'))->assertOk()->assertDontSee('Hidden live record');
        $this->assertSame([10, 5, 5, 2, 8], array_column($dashboard->viewData('cards'), 'count'));
        $list = $this->get(route('projects.index'))->assertOk();
        $this->assertSame(10, $list->viewData('records')->total());
        foreach ($dashboard->viewData('rows') as $row) {
            $detail = $this->get($row['url'])->assertOk()->assertSee($row['project']->data['name']);
            $this->assertSame($row['health'], $detail->viewData('row')['health']);
            $this->assertSame($row['has_issues'], $detail->viewData('openIssues')->isNotEmpty());
            $this->assertNotEmpty($detail->viewData('record')->data['agency']);
            if ($row['phase'] === 'Ongoing') {
                $finance = $this->get($row['url'].'?tab=financial')->assertOk()->viewData('finance');
                $this->assertEquals($row['project']->data['cost'], $finance['cost']);
            }
        }
        $this->assertCount(4, $this->get(route('dashboard.projects.show', 'IDS-2026-001'))->viewData('openIssues'));
    }
}
