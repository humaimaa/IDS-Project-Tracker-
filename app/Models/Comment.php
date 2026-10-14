<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    protected $table = 'comments';

    protected $fillable = ['project_id', 'parent_id', 'author_id', 'component_id', 'subcomponent_id', 'body', 'version'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'component_id');
    }

    public function subcomponent(): BelongsTo
    {
        return $this->belongsTo(Subcomponent::class, 'subcomponent_id');
    }
}
