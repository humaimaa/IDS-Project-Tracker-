<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarterlyFinancial extends Model
{
    protected $table = 'quarterly_financials';

    protected $fillable = ['report_id', 'period_id', 'currency_code', 'planned_disbursement', 'actual_disbursement', 'expenditure_amount'];

    protected function casts(): array
    {
        return ['planned_disbursement' => 'decimal:2', 'actual_disbursement' => 'decimal:2', 'expenditure_amount' => 'decimal:2'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(FinancialReport::class, 'report_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ReportingPeriod::class, 'period_id');
    }
}
