<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IssueRule extends Model
{
    protected $table = 'issue_rules';

    protected $fillable = ['code', 'name', 'conditions', 'active'];

    protected function casts(): array
    {
        return ['conditions' => 'array', 'active' => 'boolean'];
    }
}
