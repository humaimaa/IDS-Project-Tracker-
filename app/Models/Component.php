<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Component extends Model
{
    public function subcomponents(): HasMany
    {
        return $this->hasMany(Subcomponent::class)->orderBy('sequence_no');
    }

    use HasFactory;

    protected $table = 'components';

    protected $fillable = ['project_id', 'name', 'sequence_no', 'start_date', 'baseline_end', 'effective_end', 'lifecycle', 'weight', 'reported_progress', 'responsible_office', 'remarks', 'version'];

    protected function casts(): array
    {
        return ['sequence_no' => 'integer', 'start_date' => 'date', 'baseline_end' => 'date', 'effective_end' => 'date', 'weight' => 'decimal:2', 'reported_progress' => 'decimal:2', 'version' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
