<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public function components(): HasMany
    {
        return $this->hasMany(Component::class)->orderBy('sequence_no');
    }

    public function pipelineSteps(): HasMany
    {
        return $this->hasMany(ProjectPipelineStep::class)->orderBy('sort_order');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'project_organizations')->withPivot('role');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'project_contacts')->withPivot('responsibility', 'is_primary');
    }

    public function costVersions(): HasMany
    {
        return $this->hasMany(ProjectCostVersion::class)->orderByDesc('version_no');
    }

    public function financialReports(): HasMany
    {
        return $this->hasMany(FinancialReport::class)->orderByDesc('revision_no');
    }

    public function financingAgreements(): HasMany
    {
        return $this->hasMany(FinancingAgreement::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function getDataAttribute(): array
    {
        $cost = $this->costVersions->first();

        return ['reference' => $this->reference_code, 'name' => $this->name, 'stage' => $this->phase, 'status' => $this->lifecycle, 'is_flagship' => $this->is_flagship,
            'description' => $this->objective, 'agency' => $this->implementing_agency ?? $this->organizations->firstWhere('pivot.role', 'implementing')?->name,
            'partners' => $this->organizations->where('pivot.role', 'donor')->pluck('name')->all(),
            'sectors' => [$this->sector->name], 'districts' => $this->districts->isNotEmpty() ? $this->districts->pluck('name')->all() : [$this->district->name], 'cost' => $cost?->total_cost, 'currency' => $cost?->currency_code,
            'start' => $this->start_date?->toDateString(), 'completion' => $this->current_end_date?->toDateString(), 'officer' => $this->contacts->first()?->name];
    }

    use HasFactory;

    protected $table = 'projects';

    protected $fillable = ['reference_code', 'name', 'objective', 'implementing_agency', 'phase', 'lifecycle', 'is_flagship', 'sector_id', 'district_id', 'start_date', 'original_end_date', 'current_end_date', 'approval_forum', 'approval_date', 'funding_type', 'source_rating', 'reported_progress', 'progress_as_of', 'progress_scope', 'source_notes'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'is_flagship' => 'boolean', 'start_date' => 'date', 'original_end_date' => 'date', 'current_end_date' => 'date', 'approval_date' => 'date', 'reported_progress' => 'decimal:2', 'progress_as_of' => 'date'];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }

    public function districts(): BelongsToMany
    {
        return $this->belongsToMany(District::class, 'project_district')->orderBy('name');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }
}
