<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressUpdate extends Model
{
    protected $table = 'progress_updates';

    protected $fillable = ['component_id', 'subcomponent_id', 'as_of_date', 'progress_percent', 'status', 'entered_by', 'remarks'];

    protected function casts(): array
    {
        return ['as_of_date' => 'date', 'progress_percent' => 'decimal:2'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'component_id');
    }

    public function subcomponent(): BelongsTo
    {
        return $this->belongsTo(Subcomponent::class, 'subcomponent_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
