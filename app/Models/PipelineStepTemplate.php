<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineStepTemplate extends Model
{
    protected $table = 'pipeline_step_templates';

    protected $fillable = ['name', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }
}
