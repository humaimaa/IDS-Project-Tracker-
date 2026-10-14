<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCostVersion extends Model
{
    protected $table = 'project_cost_versions';

    protected $fillable = ['project_id', 'version_no', 'currency_code', 'total_cost', 'foreign_cost', 'local_cost', 'effective_on', 'supersedes_id'];

    protected function casts(): array
    {
        return ['version_no' => 'integer', 'total_cost' => 'decimal:2', 'foreign_cost' => 'decimal:2', 'local_cost' => 'decimal:2', 'effective_on' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(ProjectCostVersion::class, 'supersedes_id');
    }
}
