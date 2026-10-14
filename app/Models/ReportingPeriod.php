<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingPeriod extends Model
{
    protected $table = 'reporting_periods';

    protected $fillable = ['financial_year_id', 'quarter_no', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return ['quarter_no' => 'integer', 'start_date' => 'date', 'end_date' => 'date'];
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class, 'financial_year_id');
    }
}
