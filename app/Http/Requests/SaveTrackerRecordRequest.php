<?php

namespace App\Http\Requests;

use App\Models\TrackerRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTrackerRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public static function fieldOptions(string $module, ?TrackerRecord $record = null): array
    {
        $options = [];
        $references = TrackerRecord::where('module', 'references')->get();
        $projects = TrackerRecord::where('module', 'projects')->get()->pluck('data.name')->filter()->all();
        if ($module === 'issues') {
            $projects = array_merge($projects, array_column(config('dashboard.projects'), 'name'));
        }
        $types = ['agency' => 'Implementing agency', 'districts' => 'District', 'sectors' => 'Sector', 'partners' => 'Development partner'];
        foreach (config("tracker.{$module}.fields") as $field) {
            [$name, $label, $type] = $field;
            if (! in_array($type, ['select', 'multiselect'])) {
                continue;
            }
            $values = $field[4];
            if ($name === 'project_id') {
                $values = TrackerRecord::where('module', 'projects')->pluck('id')->map(fn ($id): string => (string) $id)->all();
            }
            if ($name === 'parent_id') {
                $values = TrackerRecord::where('module', 'activities')->pluck('id')->map(fn ($id): string => (string) $id)->all();
            }
            if ($name === 'template_id') {
                $templates = config('tracker.projects.activity_templates');
                $values = $module === 'activities' ? array_keys($templates) : array_merge(...array_map(fn (array $template): array => array_keys($template['subactivities']), array_values($templates)));
            }
            if (isset($types[$name])) {
                $values = array_merge($values, $references->filter(fn ($item) => ($item->data['type'] ?? null) === $types[$name] && ($item->data['status'] ?? '') === 'Active')->pluck('data.name')->all());
            }
            if (in_array($name, ['project', 'projects'])) {
                $values = array_merge($values, $projects);
            }
            if (in_array($name, ['sectors', 'partners'])) {
                $values = array_merge($values, TrackerRecord::where('module', $name)->get()->pluck('data.name')->all());
            }
            if ($name === 'role') {
                $values = TrackerRecord::where('module', 'roles')->get()->pluck('data.name')->all();
            }
            if (isset($types[$name]) || in_array($name, ['project', 'projects'])) {
                $existing = $record?->data[$name] ?? [];
                $values = array_merge($values, is_array($existing) ? $existing : [$existing]);
            }
            $options[$name] = array_values(array_unique(array_filter($values)));
        }

        return $options;
    }

    protected function prepareForValidation(): void
    {
        $module = explode('.', $this->route()->getName())[0];
        foreach (config("tracker.{$module}.fields") as $field) {
            if ($field[2] === 'multiselect' && ! $this->has($field[0])) {
                $this->merge([$field[0] => []]);
            }
        }
        foreach (['meetings' => 'actions'] as $type => $key) {
            if ($module === $type && ! $this->has($key)) {
                $this->merge([$key => []]);
            }
        }
        if ($module === 'projects' && $this->filled('currency')) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }

    public function rules(): array
    {
        $module = explode('.', $this->route()->getName())[0];
        $record = $this->route('record');
        $options = self::fieldOptions($module, $record instanceof TrackerRecord ? $record : null);
        $rules = [];
        foreach (config("tracker.{$module}.fields") as $field) {
            [$name, $label, $type, $required] = $field;
            if ($type === 'multiselect') {
                $rules[$name] = [$required ? 'required' : 'present', 'array', 'max:100'];
                $rules[$name.'.*'] = ['string', 'distinct', Rule::in($options[$name])];

                continue;
            }
            $rules[$name] = [$required ? 'required' : 'nullable'];
            if ($type === 'number') {
                $rules[$name] = array_merge($rules[$name], ['numeric', 'min:0', 'max:999999999999999']);

                continue;
            }
            $rules[$name] = array_merge($rules[$name], ['string', $type === 'textarea' ? 'max:5000' : 'max:255']);
            if ($type === 'select') {
                $rules[$name][] = Rule::in($options[$name]);
            }
            if (in_array($type, ['date', 'datetime-local'])) {
                $rules[$name][] = 'date';
            }
            if (in_array($type, ['email', 'url'])) {
                $rules[$name][] = $type;
            }
        }
        foreach (['completion' => 'start', 'due' => 'start', 'actual_completion' => 'actual_start', 'period_end' => 'period_start'] as $end => $start) {
            if (isset($rules[$end]) && $this->filled($start)) {
                $rules[$end][] = 'after_or_equal:'.$start;
            }
        }
        if ($module === 'projects') {
            $rules['currency'] = ['nullable', 'required_with:cost', 'regex:/^[A-Z]{3}$/'];
            $rules['reference'][] = function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                $query = TrackerRecord::where('module', 'projects')->where('data->reference', $value);
                if ($record instanceof TrackerRecord) {
                    $query->whereKeyNot($record->id);
                }
                if ($query->exists()) {
                    $fail('The reference number has already been used.');
                }
            };
            if ($record instanceof TrackerRecord && $record->data['stage'] !== $this->input('stage')) {
                $rules['stage_change_reason'] = ['required', 'string', 'max:5000'];
            }
            $rules['activities'] = ['prohibited'];
        }
        if (in_array($module, ['activities', 'subactivities'])) {
            $rules['name'] = [$this->input('type') === 'Custom' ? 'required' : 'nullable', 'string', 'max:255'];
            $rules['template_id'] = $this->input('type') === 'Predefined'
                ? ['required', 'string', Rule::in($options['template_id'])]
                : ['nullable', 'prohibited'];
        }
        if ($module === 'roles') {
            $rules['name'][] = function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                $query = TrackerRecord::where('module', 'roles')->where('data->name', $value);
                if ($record instanceof TrackerRecord) {
                    $query->whereKeyNot($record->id);
                }
                if ($query->exists()) {
                    $fail('A role with this name already exists.');
                }
            };
        }
        if ($module === 'meetings') {
            $rules['actions'] = ['array', 'max:100'];
            $rules['actions.*'] = ['array:description,officer,due,status,remarks,activity,issue'];
            foreach (['description', 'officer'] as $key) {
                $rules['actions.*.'.$key] = ['required', 'string', 'max:5000'];
            }
            foreach (['remarks', 'activity', 'issue'] as $key) {
                $rules['actions.*.'.$key] = ['nullable', 'string', 'max:5000'];
            }
            $rules['actions.*.due'] = ['required', 'date'];
            $rules['actions.*.status'] = ['required', Rule::in(['Not Started', 'In Progress', 'Completed', 'On Hold', 'Not Applicable'])];
        }

        if (in_array($module, ['projects', 'activities', 'subactivities', 'issues', 'meetings'])) {
            $rules['documents'] = ['sometimes', 'array', 'max:5'];
            $rules['documents.*'] = ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,txt'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $module = explode('.', $this->route()->getName())[0];
            if (! in_array($module, ['activities', 'subactivities'])) {
                return;
            }
            $parent = $module === 'subactivities' ? TrackerRecord::where('module', 'activities')->find($this->input('parent_id')) : null;
            if ($module === 'subactivities' && (! $parent || (string) ($parent->data['project_id'] ?? '') !== $this->input('project_id') || ($parent->data['stage'] ?? '') !== $this->input('stage'))) {
                $validator->errors()->add('parent_id', 'Choose a parent activity in the selected project and stage.');

                return;
            }
            if ($this->input('type') === 'Predefined') {
                $templates = config('tracker.projects.activity_templates');
                $templateId = $this->input('template_id');
                $valid = $module === 'activities'
                    ? isset($templates[$templateId]) && $templates[$templateId]['stage'] === $this->input('stage')
                    : isset($templates[$parent->data['template_id'] ?? '']['subactivities'][$templateId]);
                if (! $valid) {
                    $validator->errors()->add('template_id', 'Choose a predefined item for the selected stage and parent activity.');
                }
                $query = TrackerRecord::where('module', $module)->where('data->project_id', $this->input('project_id'))->where('data->template_id', $templateId);
                if ($module === 'subactivities') {
                    $query->where('data->parent_id', $this->input('parent_id'));
                }
                if ($this->route('record') instanceof TrackerRecord) {
                    $query->whereKeyNot($this->route('record')->id);
                }
                if ($query->exists()) {
                    $validator->errors()->add('template_id', 'This predefined item already exists. Edit it from the list.');
                }
            }
            $record = $this->route('record');
            if ($module === 'activities' && $record instanceof TrackerRecord && TrackerRecord::where('module', 'subactivities')->where('data->parent_id', (string) $record->id)->exists()) {
                foreach (['project_id', 'stage', 'type', 'template_id'] as $key) {
                    if (($record->data[$key] ?? '') !== ($this->input($key) ?? '')) {
                        $validator->errors()->add($key, 'Reassign or delete the sub-activities before changing the parent scope or type.');
                    }
                }
            }
        }];
    }
}
