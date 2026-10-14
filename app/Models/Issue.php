<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Issue extends Model
{
    protected $table = 'issues';

    protected $fillable = ['project_id', 'pipeline_step_id', 'component_id', 'subcomponent_id', 'rule_id', 'title', 'description', 'severity', 'status', 'due_date', 'raised_at', 'resolved_at', 'resolution', 'responsible_entity', 'source_key'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'raised_at' => 'date', 'resolved_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function pipelineStep(): BelongsTo
    {
        return $this->belongsTo(ProjectPipelineStep::class, 'pipeline_step_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'component_id');
    }

    public function subcomponent(): BelongsTo
    {
        return $this->belongsTo(Subcomponent::class, 'subcomponent_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(IssueRule::class, 'rule_id');
    }
}
