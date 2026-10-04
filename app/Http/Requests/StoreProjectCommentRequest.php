<?php

namespace App\Http\Requests;

use App\Models\TrackerRecord;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function projectKey(): string
    {
        $record = $this->route('record');
        if ($record instanceof TrackerRecord) {
            abort_unless($record->module === 'projects', 404);

            return 'project:'.$record->id;
        }
        $sample = $this->route('sample');
        abort_unless(is_array(config('project_samples.'.$sample)), 404);

        return 'sample:'.$sample;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $key = $this->projectKey();
        $record = $this->route('record');
        $activityKeys = $record instanceof TrackerRecord
            ? TrackerRecord::where('module', 'activities')->where('data->project_id', (string) $record->id)->pluck('id')->map(fn (int $id): string => 'activity:'.$id)->all()
            : array_map(fn (string $id): string => 'activity:'.$id, array_keys(config('tracker.projects.activity_templates')));

        return [
            'body' => ['required', 'string', 'max:3000'],
            'context' => ['required', 'string', Rule::in(['project', 'stage:1', 'stage:2', 'stage:3', ...$activityKeys])],
            'parent_id' => ['nullable', 'integer', Rule::exists(TrackerRecord::class, 'id')->where(fn (Builder $query): Builder => $query->where('module', 'project_comments')->where('data->project_key', $key)->whereNull('data->parent_id'))],
        ];
    }
}
