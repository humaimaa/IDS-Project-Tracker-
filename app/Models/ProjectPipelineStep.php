<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPipelineStep extends Model
{
    protected $table = 'project_pipeline_steps';

    protected $attributes = ['version' => 1];

    protected $fillable = ['version', 'project_id', 'template_id', 'name', 'sort_order', 'status', 'start_date', 'responsible_office', 'planned_date', 'completed_date', 'is_current', 'remarks'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'sort_order' => 'integer', 'start_date' => 'date', 'planned_date' => 'date', 'completed_date' => 'date', 'is_current' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $step): void {
            if ($step->isDirty() && ! $step->isDirty('version')) {
                $step->version = (int) $step->getOriginal('version') + 1;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PipelineStepTemplate::class, 'template_id');
    }
}
