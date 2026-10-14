<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtensionRequest extends Model
{
    public function decisions(): HasMany
    {
        return $this->hasMany(ExtensionDecision::class, 'request_id')->orderBy('sequence_no');
    }

    protected $table = 'extension_requests';

    protected $fillable = ['pipeline_step_id', 'component_id', 'subcomponent_id', 'requester_id', 'reviewer_id', 'old_end_date', 'requested_end_date', 'reason', 'impact', 'status', 'target_version', 'supersedes_id'];

    protected function casts(): array
    {
        return ['old_end_date' => 'date', 'requested_end_date' => 'date', 'target_version' => 'integer'];
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(ExtensionRequest::class, 'supersedes_id');
    }
}
