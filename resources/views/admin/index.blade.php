@php
    $meta = [
        'title' => 'Admin - Gematrix',
        'robots' => 'noindex,nofollow',
        'canonical' => url('/admin'),
    ];
@endphp
<x-layouts.app :title="'Admin'" :meta="$meta">
    <section class="card reveal">
        <div class="split-head">
            <div>
                <h1><i class="fa-solid fa-shield-halved"></i> Admin Console</h1>
                <p class="subtitle">Phrases: {{ number_format($total) }} | Approved: {{ number_format($approvedCount) }} | Pending: {{ number_format($pending->total()) }}</p>
            </div>
            <div class="flex gap-2">
                <a href="/admin/analytics" class="btn btn-primary btn-sm"><i class="fa-solid fa-chart-line"></i> Analytics</a>
                <a href="/admin/news-crawler" class="btn btn-outline btn-sm"><i class="fa-solid fa-newspaper"></i> News Crawler</a>
            </div>
        </div>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-sliders"></i> Cipher Settings</h3>
        <form method="POST" action="/admin/ciphers" class="stack-sm">
            @csrf
            <div class="chips">
                @foreach($allCiphers as $key => $label)
                    <label class="chip">
                        <input type="checkbox" name="enabled_ciphers[]" value="{{ $key }}" @checked(in_array($key, $enabledCiphers, true))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <button class="btn btn-primary btn-sm">Save Ciphers</button>
        </form>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-file-csv"></i> CSV Import</h3>
        <form method="POST" action="/admin/import" enctype="multipart/form-data" class="stack-sm">
            @csrf
            <input class="file-input file-input-bordered file-input-sm" type="file" name="csv" accept=".csv,.txt" required>
            <button class="btn btn-primary btn-sm">Import as pending</button>
        </form>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-list-check"></i> Pending moderation</h3>
        <div class="table-wrap">
            <table class="table table-zebra">
                <thead><tr><th>Phrase</th><th>English</th><th>Approved</th></tr></thead>
                <tbody>
                @foreach($pending as $phrase)
                    <tr>
                        <td>{{ $phrase->phrase }}</td>
                        <td>{{ $phrase->english_ordinal }}</td>
                        <td>
                            <form method="POST" action="/admin/approve/{{ $phrase->id }}">
                                @csrf
                                <button class="btn btn-xs btn-outline">{{ $phrase->approved ? 'Unapprove' : 'Approve' }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pager">{{ $pending->links() }}</div>
    </section>
</x-layouts.app>
