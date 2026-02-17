@php
    $meta = [
        'title' => 'Narrative: '.$narrative->title.' - Gematrix',
        'robots' => 'noindex,nofollow',
        'canonical' => url('/workspace/narrative/'.$narrative->id),
    ];
@endphp
<x-layouts.app :title="$narrative->title" :meta="$meta">
    <section class="card reveal">
        <div class="split-head">
            <div>
                <h1><i class="fa-solid fa-route"></i> {{ $narrative->title }}</h1>
                <p class="subtitle">Evidence trail with {{ count($narrative->steps ?? []) }} steps, {{ count($narrative->linked_phrases ?? []) }} linked phrases, and {{ number_format((int) $narrative->view_count) }} views.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a class="btn btn-outline btn-sm" href="/workspace"><i class="fa-solid fa-arrow-left"></i> Back to Workspace</a>
                <a class="btn btn-outline btn-sm" href="/workspace/narrative/{{ $narrative->id }}/export/markdown"><i class="fa-solid fa-file-lines"></i> Export Markdown</a>
                <a class="btn btn-primary btn-sm" href="/workspace/narrative/{{ $narrative->id }}/export/print" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i> PDF Print View</a>
            </div>
        </div>
        @if($narrative->summary)
            <p class="subtitle mt-2">{{ $narrative->summary }}</p>
        @endif
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-list-ol"></i> Research Steps</h3>
        @if(!empty($narrative->steps))
            <ol class="narrative-steps">
                @foreach($narrative->steps as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        @else
            <p class="empty">No steps documented yet.</p>
        @endif
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-link"></i> Linked Phrases</h3>
        @if(($narrative->linked_phrases ?? []) !== [])
            <div class="chips">
                @foreach($narrative->linked_phrases as $linked)
                    <a class="chip-link" href="/calculate?q={{ urlencode($linked) }}">{{ $linked }}</a>
                @endforeach
            </div>
        @else
            <p class="empty">No linked phrases.</p>
        @endif

        @if($phraseEntries->count() > 0)
            <div class="table-wrap mt-3">
                <table class="table table-zebra">
                    <thead>
                    <tr><th>Phrase</th><th>Note</th><th>Open</th></tr>
                    </thead>
                    <tbody>
                    @foreach($phraseEntries as $entry)
                        <tr>
                            <td>{{ $entry->phrase }}</td>
                            <td>{{ $entry->note ?: '-' }}</td>
                            <td><a class="muted-link" href="/calculate?q={{ urlencode($entry->phrase) }}">Analyze</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
