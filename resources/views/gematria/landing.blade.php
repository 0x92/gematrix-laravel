@php
    $landingMeta = [
        'title' => 'Gematrix - Professional Gematria Calculator & Phrase Explorer',
        'description' => 'Use Gematrix to calculate gematria values across multiple ciphers, find matching phrases, and inspect transparent letter-by-letter breakdowns.',
        'canonical' => url('/'),
        'og_type' => 'website',
        'json_ld' => [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => 'Gematrix',
                'url' => url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => url('/calculate').'?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => 'Gematrix',
                'applicationCategory' => 'EducationalApplication',
                'operatingSystem' => 'Web',
                'url' => url('/'),
                'description' => 'Gematria research platform with multi-cipher scoring, explorer filters, ranked phrase matches, and API endpoints.',
            ],
        ],
    ];
@endphp
<x-layouts.app :title="'Gematrix - Terminal Gematria'" :meta="$landingMeta">
    <section class="card reveal landing-hero">
        <p class="kicker"><i class="fa-solid fa-shield"></i> Directorate Cipher Operations Console</p>
        <h1>Decode hidden structures in language for intelligence-grade pattern discovery.</h1>
        <p class="subtitle">Gematrix Ops combines multi-cipher analysis, ranked phrase links, graph intelligence, and operator workflows in one mission control interface.</p>
        <div class="stats-row">
            <div><i class="fa-solid fa-database"></i> Indexed phrases: <strong>{{ number_format($totalPhrases) }}</strong></div>
            <div><i class="fa-solid fa-code"></i> API endpoint: <code>/api/calculate</code></div>
            <div><i class="fa-solid fa-compass"></i> Deep search: <a class="muted-link" href="/explorer">Explorer</a></div>
        </div>
        <div class="hero-actions">
            <a class="btn btn-primary" href="/calculate?q=computer"><i class="fa-solid fa-satellite-dish"></i> Launch Mission Console</a>
            <a class="btn btn-outline" href="/explorer"><i class="fa-solid fa-table-list"></i> Open Intelligence Explorer</a>
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-diagram-project"></i> How It Works</h3>
        </div>
        <div class="legend-grid">
            <article class="legend-card">
                <p class="legend-title"><i class="fa-solid fa-keyboard"></i> 1. Enter phrase</p>
                <p class="legend-desc">Type any word or sentence. Gematrix normalizes input and computes active ciphers immediately.</p>
            </article>
            <article class="legend-card">
                <p class="legend-title"><i class="fa-solid fa-calculator"></i> 2. Compare ciphers</p>
                <p class="legend-desc">Review totals across ordinal, reduction, prime, satanic and reference mappings in one table.</p>
            </article>
            <article class="legend-card">
                <p class="legend-title"><i class="fa-solid fa-link"></i> 3. Trace connections</p>
                <p class="legend-desc">Jump to detail pages and discover related phrases sharing identical values by selected cipher.</p>
            </article>
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-layer-group"></i> Cipher Families</h3>
        </div>
        <div class="chips">
            @foreach($cipherLabels as $label)
                <span class="chip-link">{{ $label }}</span>
            @endforeach
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-fire"></i> Trending Queries (14d)</h3>
        </div>
        <div class="chips">
            @forelse($trending as $item)
                <a class="chip-link" href="/calculate?q={{ urlencode($item->query_norm) }}">{{ $item->query_norm }} <span class="badge badge-primary badge-sm">{{ number_format((int) $item->aggregate) }}</span></a>
            @empty
                <span class="empty">No trend data available yet.</span>
            @endforelse
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-rocket"></i> Built For Serious Research</h3>
        </div>
        <div class="table-wrap">
            <table class="table table-zebra">
                <thead>
                <tr><th>Capability</th><th>What You Get</th></tr>
                </thead>
                <tbody>
                <tr><td>Calculator Core</td><td>Fast phrase scoring with configurable cipher sets.</td></tr>
                <tr><td>Entry Detail</td><td>Breakdown by letters, keyboard visualizer, and matching connections.</td></tr>
                <tr><td>Explorer</td><td>Filter by phrase/cipher/value and sort across large datasets.</td></tr>
                <tr><td>Analytics</td><td>Search trends, click-through behavior, and rising keywords.</td></tr>
                <tr><td>API</td><td>Integrate with external tools via `/api/calculate`, `/api/explorer`, `/api/suggest`.</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-brain"></i> AI & Data Access</h3>
        </div>
        <p class="subtitle">Machine-readable endpoints are available for search engines, crawlers, and LLM tools.</p>
        <div class="chips">
            <a class="chip-link" href="/sitemap.xml">sitemap.xml</a>
            <a class="chip-link" href="/robots.txt">robots.txt</a>
            <a class="chip-link" href="/llms.txt">llms.txt</a>
            <a class="chip-link" href="/api/calculate?q=computer">API example</a>
        </div>
    </section>
</x-layouts.app>
