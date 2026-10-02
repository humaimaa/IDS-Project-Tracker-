<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            'concept-note' => ['name' => 'Prepare concept note', 'stage' => 'Concept', 'subactivities' => ['identify-needs' => 'Identify project needs', 'define-objectives' => 'Define objectives and scope']],
            'concept-review' => ['name' => 'Review project concept', 'stage' => 'Concept', 'subactivities' => ['consult-stakeholders' => 'Consult stakeholders', 'approve-concept' => 'Obtain concept endorsement']],
            'prepare-pc1' => ['name' => 'Prepare PC-I', 'stage' => 'PC-I Development', 'subactivities' => ['feasibility' => 'Complete feasibility assessment', 'cost-estimates' => 'Prepare cost estimates']],
            'approve-pc1' => ['name' => 'Review and approve PC-I', 'stage' => 'PC-I Development', 'subactivities' => ['technical-review' => 'Complete technical review', 'approval' => 'Obtain approval']],
            'procurement' => ['name' => 'Procurement and mobilization', 'stage' => 'Implementation', 'subactivities' => ['procurement-plan' => 'Prepare procurement plan', 'mobilization' => 'Mobilize implementation team']],
            'delivery' => ['name' => 'Delivery and monitoring', 'stage' => 'Implementation', 'subactivities' => ['progress-review' => 'Review implementation progress', 'completion-review' => 'Complete final review and handover']],
        ];
        DB::transaction(function () use ($templates): void {
            foreach (DB::table('tracker_records')->where('module', 'projects')->orderBy('id')->get() as $project) {
                $data = json_decode($project->data, true, 512, JSON_THROW_ON_ERROR);
                $activities = $data['activities'] ?? [];
                foreach ($templates as $templateId => $template) {
                    $found = false;
                    foreach ($activities as &$activity) {
                        if (($activity['template_id'] ?? null) === $templateId) {
                            $activity['subactivities'] = $this->withChildren($activity['subactivities'] ?? [], $template['subactivities']);
                            $found = true;
                            break;
                        }
                    }
                    unset($activity);
                    if (! $found) {
                        $activities[] = [
                            'template_id' => $templateId, 'name' => $template['name'], 'stage' => $template['stage'],
                            'status' => 'Not Started', 'subactivities' => $this->withChildren([], $template['subactivities']),
                        ];
                    }
                }
                foreach ($activities as $activity) {
                    $children = $activity['subactivities'] ?? [];
                    unset($activity['subactivities']);
                    $activity['type'] = empty($activity['template_id']) ? 'Custom' : 'Predefined';
                    $activity['project_id'] = (string) $project->id;
                    $activity['project'] = $data['name'];
                    $parentId = $this->insertItem('activities', $activity);
                    foreach ($children as $child) {
                        if (is_string($child)) {
                            $child = ['name' => $child, 'status' => 'Not Started'];
                        }
                        $this->insertItem('subactivities', array_merge($child, [
                            'type' => empty($child['template_id']) ? 'Custom' : 'Predefined',
                            'project_id' => (string) $project->id, 'project' => $data['name'],
                            'stage' => $activity['stage'], 'parent_id' => (string) $parentId, 'parent_activity' => $activity['name'],
                        ]));
                    }
                }
                unset($data['activities']);
                DB::table('tracker_records')->where('id', $project->id)->update(['data' => json_encode($data, JSON_THROW_ON_ERROR)]);
            }
            foreach (DB::table('tracker_records')->where('module', 'activities')->get() as $item) {
                $data = json_decode($item->data, true, 512, JSON_THROW_ON_ERROR);
                if (isset($data['project_id'])) {
                    continue;
                }
                $project = DB::table('tracker_records')->where('module', 'projects')->where('data->name', $data['project'] ?? '')->first();
                $data['type'] = 'Custom';
                $data['project_id'] = $project ? (string) $project->id : '';
                DB::table('tracker_records')->where('id', $item->id)->update(['data' => json_encode($data, JSON_THROW_ON_ERROR)]);
            }
            foreach (DB::table('tracker_records')->where('module', 'activities')->get() as $item) {
                $data = json_decode($item->data, true, 512, JSON_THROW_ON_ERROR);
                if (empty($data['parent_activity'])) {
                    continue;
                }
                $parent = DB::table('tracker_records')->where('module', 'activities')
                    ->where('data->project_id', $data['project_id'])->where('data->project', $data['project'] ?? '')->where('data->stage', $data['stage'])
                    ->where('data->name', $data['parent_activity'])->where('id', '!=', $item->id)->first();
                $data['parent_id'] = $parent ? (string) $parent->id : '';
                DB::table('tracker_records')->where('id', $item->id)->update(['module' => 'subactivities', 'data' => json_encode($data, JSON_THROW_ON_ERROR)]);
            }
        });
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $children
     * @param  array<string, string>  $defaults
     * @return array<int, array<string, mixed>|string>
     */
    private function withChildren(array $children, array $defaults): array
    {
        foreach ($defaults as $id => $name) {
            if (! in_array($id, array_column($children, 'template_id'), true)) {
                $children[] = ['template_id' => $id, 'name' => $name, 'status' => 'Not Started'];
            }
        }

        return $children;
    }

    /** @param array<string, mixed> $data */
    private function insertItem(string $module, array $data): int
    {
        return DB::table('tracker_records')->insertGetId([
            'module' => $module, 'data' => json_encode($data, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        throw new RuntimeException('This data migration cannot be reversed safely after activities have been edited. Restore a database backup instead.');
    }
};
