<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLink extends Model
{
    protected $table = 'document_links';

    protected $fillable = ['document_id', 'project_id', 'component_id', 'subcomponent_id', 'issue_id', 'meeting_id', 'extension_request_id', 'financial_report_id'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'component_id');
    }

    public function subcomponent(): BelongsTo
    {
        return $this->belongsTo(Subcomponent::class, 'subcomponent_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'issue_id');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function extensionRequest(): BelongsTo
    {
        return $this->belongsTo(ExtensionRequest::class, 'extension_request_id');
    }

    public function financialReport(): BelongsTo
    {
        return $this->belongsTo(FinancialReport::class, 'financial_report_id');
    }
}
