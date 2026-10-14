<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $table = 'organizations';

    protected $fillable = ['code', 'name', 'organization_type', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
