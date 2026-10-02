<section class="space-y-4 border-t border-slate-100 pt-5" id="meeting-actions">
    <h2 class="font-display text-lg font-bold">Follow-up actions</h2>
    <div class="space-y-4" id="meeting-action-list"></div>
    <button class="rounded-lg border border-teal px-4 py-2 text-sm font-semibold text-teal" type="button" id="add-meeting-action">Add follow-up action</button>
</section>
@push('scripts')
<script>window.meetingActions = {{ Illuminate\Support\Js::from(old('actions', $record?->data['actions'] ?? [])) }};</script>
@endpush
