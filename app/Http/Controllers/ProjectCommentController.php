<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectCommentRequest;
use App\Models\TrackerRecord;
use Illuminate\Http\RedirectResponse;

class ProjectCommentController extends Controller
{
    public function store(StoreProjectCommentRequest $request, TrackerRecord $record): RedirectResponse
    {
        return $this->persist($request);
    }

    public function sampleStore(StoreProjectCommentRequest $request, string $sample): RedirectResponse
    {
        return $this->persist($request);
    }

    private function persist(StoreProjectCommentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $parent = ! empty($data['parent_id']) ? TrackerRecord::findOrFail($data['parent_id']) : null;
        $context = $parent?->data['context'] ?? $data['context'];
        TrackerRecord::create(['module' => 'project_comments', 'data' => [
            'project_key' => $request->projectKey(), 'context' => $context,
            'parent_id' => $parent ? (string) $parent->id : null,
            'body' => $data['body'], 'author_id' => (string) $request->user()->id, 'author_name' => $request->user()->name,
        ]]);
        $record = $request->route('record');
        $url = $record instanceof TrackerRecord
            ? route('projects.show', ['record' => $record, 'context' => $context])
            : route('projects.sample-show', ['sample' => $request->route('sample'), 'context' => $context]);

        return redirect($url.'#project-comments')->with('success', $parent ? 'Reply posted.' : 'Comment posted.');
    }
}
