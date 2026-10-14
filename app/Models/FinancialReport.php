<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialReport extends Model
{
    public function annuals(): HasMany
    {
        return $this->hasMany(AnnualFinancial::class, 'report_id');
    }

    public function quarters(): HasMany
    {
        return $this->hasMany(QuarterlyFinancial::class, 'report_id');
    }

    protected $table = 'financial_reports';

    protected $fillable = ['project_id', 'financial_year_id', 'currency_code', 'as_of_date', 'revision_no', 'source_notes'];

    protected function casts(): array
    {
        return ['as_of_date' => 'date', 'revision_no' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class, 'financial_year_id');
    }
}
