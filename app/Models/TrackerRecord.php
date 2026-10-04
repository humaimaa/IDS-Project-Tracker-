<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackerRecord extends Model
{
    use HasFactory;

    protected $fillable = ['module', 'data'];

    public function createDefaultActivities(): void
    {
        $existing = self::where('module', 'activities')->where('data->project_id', (string) $this->id)->get()->keyBy('data.template_id');
        foreach (config('tracker.projects.activity_templates') as $templateId => $template) {
            if ($existing->has($templateId)) {
                $parent = $existing->get($templateId);
                if (($parent->data['name'] ?? '') === ($template['legacy_name'] ?? null)) {
                    $parent->update(['data' => array_replace($parent->data, ['name' => $template['name']])]);
                    foreach (self::where('module', 'subactivities')->where('data->parent_id', (string) $parent->id)->get() as $child) {
                        $child->update(['data' => array_replace($child->data, ['parent_activity' => $template['name']])]);
                    }
                }

                continue;
            }
            $children = $template['subactivities'];
            $activity = ['template_id' => $templateId, 'name' => $template['name'], 'stage' => $template['stage'], 'status' => 'Not Started'];
            $activity['type'] = 'Predefined';
            $activity['project_id'] = (string) $this->id;
            $activity['project'] = $this->data['name'];
            $parent = self::create(['module' => 'activities', 'data' => $activity]);
            foreach ($children as $childId => $childName) {
                self::create(['module' => 'subactivities', 'data' => [
                    'template_id' => $childId, 'name' => $childName, 'status' => 'Not Started',
                    'type' => 'Predefined', 'project_id' => (string) $this->id,
                    'project' => $this->data['name'], 'stage' => $activity['stage'],
                    'parent_id' => (string) $parent->id, 'parent_activity' => $activity['name'],
                ]]);
            }
        }
    }

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
