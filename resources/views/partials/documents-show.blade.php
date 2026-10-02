@if(!empty($record->data['attachments']))
<section class="panel mt-5 space-y-3 p-6">
    <h2 class="font-display text-lg font-bold">Supporting documents</h2>
    @foreach($record->data['attachments'] as $document)
        <p class="text-sm">{{ $document['title'] }} <span class="text-slate-500">· Uploaded {{ $document['uploaded_at'] }}</span></p>
    @endforeach
</section>
@endif
