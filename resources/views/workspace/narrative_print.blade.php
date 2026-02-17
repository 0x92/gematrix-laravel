<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Narrative Export - {{ $narrative->title }}</title>
    <style>
        body { font-family: Georgia, serif; margin: 28px; color: #111; line-height: 1.45; }
        h1, h2 { margin: 0 0 10px; }
        .meta { margin-bottom: 18px; color: #555; }
        .section { margin-top: 18px; }
        ul, ol { margin: 8px 0 0 24px; }
        .phrase-grid { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .pill { border: 1px solid #bbb; border-radius: 999px; padding: 4px 10px; font-size: 13px; }
        .toolbar { margin-bottom: 16px; }
        .toolbar button { padding: 7px 12px; }
        @media print {
            .toolbar { display: none; }
            body { margin: 14mm; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
</div>

<h1>{{ $narrative->title }}</h1>
<p class="meta">Generated on {{ now()->format('Y-m-d H:i') }} UTC | Narrative #{{ $narrative->id }}</p>
@if($narrative->summary)
    <p>{{ $narrative->summary }}</p>
@endif

<div class="section">
    <h2>Research Steps</h2>
    @if(!empty($narrative->steps))
        <ol>
            @foreach($narrative->steps as $step)
                <li>{{ $step }}</li>
            @endforeach
        </ol>
    @else
        <p>No documented steps.</p>
    @endif
</div>

<div class="section">
    <h2>Linked Phrases</h2>
    @if(($narrative->linked_phrases ?? []) !== [])
        <div class="phrase-grid">
            @foreach($narrative->linked_phrases as $phrase)
                <span class="pill">{{ $phrase }}</span>
            @endforeach
        </div>
    @else
        <p>No linked phrases.</p>
    @endif
</div>
</body>
</html>
