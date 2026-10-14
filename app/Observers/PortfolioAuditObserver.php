<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class PortfolioAuditObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created', [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);
        if ($changes !== []) {
            $this->record($model, 'updated', array_intersect_key($model->getRawOriginal(), $changes), $changes);
        }
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getAttributes(), []);
    }

    private function record(Model $model, string $action, array $before, array $after): void
    {
        if (! config('portfolio.enabled')) {
            return;
        }
        foreach (['password', 'remember_token', 'pending_email_token_hash'] as $secret) {
            if (array_key_exists($secret, $before)) {
                $before[$secret] = '[redacted]';
            }
            if (array_key_exists($secret, $after)) {
                $after[$secret] = '[redacted]';
            }
        }
        AuditLog::create(['actor_id' => auth()->id(), 'actor_name' => auth()->user()?->name ?? 'System / data import', 'action' => $action, 'module' => $model->getTable(), 'subject_id' => $model->getKey(), 'subject_name' => $model->name ?? $model->title ?? class_basename($model).' #'.$model->getKey(), 'changes' => ['before' => $before, 'after' => $after]]);
    }
}
