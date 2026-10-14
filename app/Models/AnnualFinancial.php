<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualFinancial extends Model
{
    protected $table = 'annual_financials';

    protected $fillable = ['report_id', 'currency_code', 'total_allocation', 'expenditure_ytd', 'cumulative_expenditure', 'disbursement_target', 'disbursement_ytd'];

    protected function casts(): array
    {
        return ['total_allocation' => 'decimal:2', 'expenditure_ytd' => 'decimal:2', 'cumulative_expenditure' => 'decimal:2', 'disbursement_target' => 'decimal:2', 'disbursement_ytd' => 'decimal:2'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(FinancialReport::class, 'report_id');
    }
}
