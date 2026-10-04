<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['actor_id', 'actor_name', 'action', 'module', 'subject_id', 'subject_name', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }
}
