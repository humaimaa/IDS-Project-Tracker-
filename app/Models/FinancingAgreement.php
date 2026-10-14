<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancingAgreement extends Model
{
    protected $table = 'financing_agreements';

    protected $fillable = ['project_id', 'donor_id', 'currency_code', 'agreement_ref', 'original_commitment', 'signing_date', 'terms'];

    protected function casts(): array
    {
        return ['original_commitment' => 'decimal:2', 'signing_date' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'donor_id');
    }
}
