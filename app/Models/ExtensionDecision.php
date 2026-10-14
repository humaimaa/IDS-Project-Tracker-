<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtensionDecision extends Model
{
    protected $table = 'extension_decisions';

    protected $fillable = ['request_id', 'reviewer_id', 'sequence_no', 'decision', 'approved_end_date', 'remarks', 'decided_at'];

    protected function casts(): array
    {
        return ['sequence_no' => 'integer', 'approved_end_date' => 'date', 'decided_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ExtensionRequest::class, 'request_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
