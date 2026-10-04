<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\TrackerRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    private function record(Model $model, string $action): void
    {
        if ($model instanceof TrackerRecord && in_array($model->module, ['comment_reads', 'meeting_reads'])) {
            return;
        }
        $before = $action === 'created' ? [] : ($model instanceof TrackerRecord ? ($model->getOriginal('data') ?? []) : $model->getOriginal());
        $after = $action === 'deleted' ? [] : ($model instanceof TrackerRecord ? ($model->data ?? []) : $model->getAttributes());
        $changes = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $field) {
            if (in_array($field, ['id', 'created_at', 'updated_at', 'remember_token'])) {
                continue;
            }
            if ($action === 'updated' && ($before[$field] ?? null) === ($after[$field] ?? null)) {
                continue;
            }
            $changes[$field] = ['before' => $this->redact($field, $before[$field] ?? null), 'after' => $this->redact($field, $after[$field] ?? null)];
        }
        if ($action === 'updated' && $changes === []) {
            return;
        }
        $actor = auth()->user();
        AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? (app()->runningInConsole() ? 'System' : 'Guest / demo'),
            'action' => $action,
            'module' => $model instanceof User ? 'accounts' : $model->module,
            'subject_id' => $model->getKey(),
            'subject_name' => $after['name'] ?? $before['name'] ?? $after['project_name'] ?? $before['project_name'] ?? class_basename($model).' #'.$model->getKey(),
            'changes' => $changes,
        ]);
    }

    private function redact(string $key, mixed $value): mixed
    {
        if (preg_match('/password|token|secret|credential/i', $key)) {
            return $value === null ? null : '[redacted]';
        }
        if (is_array($value)) {
            foreach ($value as $childKey => $childValue) {
                $value[$childKey] = $this->redact((string) $childKey, $childValue);
            }
        }

        return $value;
    }
}
