<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPhaseHistory extends Model
{
    protected $table = 'project_phase_history';

    protected $fillable = ['project_id', 'from_phase', 'to_phase', 'changed_by', 'changed_at', 'reason'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
