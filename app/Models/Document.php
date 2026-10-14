<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $table = 'documents';

    protected $fillable = ['title', 'disk', 'path', 'mime_type', 'size', 'sha256', 'version', 'supersedes_id', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'version' => 'integer'];
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'supersedes_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
