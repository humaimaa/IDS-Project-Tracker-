<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subcomponent extends Model
{
    protected $table = 'subcomponents';

    protected $fillable = ['component_id', 'name', 'sequence_no', 'start_date', 'baseline_end', 'effective_end', 'lifecycle', 'weight', 'reported_progress', 'responsible_office', 'remarks', 'version'];

    protected function casts(): array
    {
        return ['sequence_no' => 'integer', 'start_date' => 'date', 'baseline_end' => 'date', 'effective_end' => 'date', 'weight' => 'decimal:2', 'reported_progress' => 'decimal:2', 'version' => 'integer'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'component_id');
    }
}
