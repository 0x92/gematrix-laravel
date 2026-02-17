@php
    $meta = [
        'title' => 'Workspace - Gematrix',
        'robots' => 'noindex,nofollow',
        'canonical' => url('/workspace'),
    ];
@endphp
<x-layouts.app :title="'Workspace'" :meta="$meta">
    <section class="card reveal">
        <h1>{{ __('messages.workspace') }}</h1>
        <p class="subtitle">{{ __('messages.workspace_subtitle') }}</p>
    </section>

    <section class="card reveal">
        <h3>{{ __('messages.preferences') }}</h3>
        <form method="POST" action="/workspace/preferences" class="stack-sm">
            @csrf
            <p class="subtitle">Dark mode and English language are enforced globally.</p>
            <div class="chips">
                @foreach($allCiphers as $key => $label)
                    <label class="chip">
                        <input type="checkbox" name="preferred_ciphers[]" value="{{ $key }}" @checked(in_array($key, auth()->user()->preferred_ciphers ?? [], true))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <button>{{ __('messages.save') }}</button>
        </form>
    </section>

    <section class="card reveal">
        <h3>{{ __('messages.favorites') }}</h3>
        <div class="chips">
            @foreach($favorites as $fav)
                <div class="chip-link">
                    <a href="/calculate?q={{ urlencode($fav->phrase) }}">{{ $fav->phrase }}</a>
                    <form method="POST" action="/workspace/favorite">@csrf @method('DELETE')<input type="hidden" name="phrase" value="{{ $fav->phrase }}"><button class="ghost-btn"><i class="fa-solid fa-xmark"></i></button></form>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card reveal">
        <h3>{{ __('messages.your_phrases') }}</h3>
        <form method="POST" action="/workspace/phrase" class="stack-sm">
            @csrf
            <input name="phrase" placeholder="{{ __('messages.phrase') }}" required>
            <textarea name="note" placeholder="Note"></textarea>
            <button>{{ __('messages.add_phrase') }}</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('messages.phrase') }}</th><th>Note</th><th></th></tr></thead>
                <tbody>
                @foreach($userPhrases as $item)
                    <tr>
                        <td>{{ $item->phrase }}</td>
                        <td>{{ $item->note }}</td>
                        <td><form method="POST" action="/workspace/phrase/{{ $item->id }}">@csrf @method('DELETE')<button class="ghost-btn"><i class="fa-solid fa-trash"></i></button></form></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card reveal">
        <h3>{{ __('messages.search_history') }}</h3>
        <div class="chips">
            @foreach($histories as $history)
                <a class="chip-link" href="/calculate?q={{ urlencode($history->query) }}">{{ $history->query }}</a>
            @endforeach
        </div>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-route"></i> Research Narratives</h3>
        <p class="subtitle">Build evidence trails with steps and linked phrases to document hidden-structure findings.</p>
        <form method="POST" action="/workspace/narrative" class="stack-sm mt-3">
            @csrf
            <input name="title" placeholder="Narrative title (e.g. 666 technology cluster)" maxlength="180" required>
            <textarea name="summary" placeholder="Short summary of the hypothesis and why this trail matters."></textarea>
            <textarea name="steps_raw" placeholder="One step per line. Example:&#10;Collect top 20 phrases sharing english_gematria=666&#10;Validate with prime and jewish overlaps&#10;Compare trend spikes over 14 days"></textarea>
            <textarea name="phrases_raw" placeholder="Linked phrases, one per line. Example:&#10;computer&#10;silicon code&#10;digital angel"></textarea>
            <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Narrative</button>
        </form>

        <div class="table-wrap mt-3">
            <table class="table table-zebra">
                <thead><tr><th>Title</th><th>Steps</th><th>Linked Phrases</th><th>Views</th><th>Export</th><th></th></tr></thead>
                <tbody>
                @forelse($narratives as $item)
                    <tr>
                        <td><a class="muted-link" href="/workspace/narrative/{{ $item->id }}">{{ $item->title }}</a></td>
                        <td>{{ count($item->steps ?? []) }}</td>
                        <td>{{ count($item->linked_phrases ?? []) }}</td>
                        <td>{{ number_format((int) $item->view_count) }}</td>
                        <td>
                            <a class="muted-link" href="/workspace/narrative/{{ $item->id }}/export/markdown">.md</a>
                            |
                            <a class="muted-link" href="/workspace/narrative/{{ $item->id }}/export/print" target="_blank" rel="noopener">PDF</a>
                        </td>
                        <td>
                            <form method="POST" action="/workspace/narrative/{{ $item->id }}">
                                @csrf
                                @method('DELETE')
                                <button class="ghost-btn"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="6">No narratives yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
