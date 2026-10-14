<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectScheduleRevision extends Model
{
    protected $table = 'project_schedule_revisions';

    protected $fillable = ['project_id', 'revision_no', 'previous_end', 'revised_end', 'source_document_id', 'reason', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['revision_no' => 'integer', 'previous_end' => 'date', 'revised_end' => 'date', 'approved_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
