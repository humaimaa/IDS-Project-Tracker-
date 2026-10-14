<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Meeting extends Model
{
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'meeting_projects');
    }

    protected $table = 'meetings';

    protected $fillable = ['name', 'scheduled_at', 'location', 'agenda', 'minutes', 'status', 'version'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'version' => 'integer'];
    }
}
