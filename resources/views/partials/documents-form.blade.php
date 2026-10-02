<section class="space-y-3 border-t border-slate-100 pt-5">
    <h2 class="font-display text-lg font-bold">Supporting documents</h2>
    <label class="block text-sm font-semibold text-slate-600">Attach documents
        <input class="field mt-2 h-auto py-2" type="file" name="documents[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.txt">
    </label>
    <p class="text-xs text-slate-500">Up to 5 files per save, 10 MB each. Filenames are used as document titles.</p>
    @foreach($record?->data['attachments'] ?? [] as $document)
        <p class="text-sm text-slate-600">{{ $document['title'] }} · Uploaded {{ $document['uploaded_at'] }}</p>
    @endforeach
    @error('documents')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
</section>
