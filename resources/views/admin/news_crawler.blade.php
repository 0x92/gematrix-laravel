@php
    $meta = [
        'title' => 'News Crawler - Admin - Gematrix',
        'robots' => 'noindex,nofollow',
        'canonical' => url('/admin/news-crawler'),
    ];
    $dailyRows = $daily->map(static fn ($row): array => [
        'day' => (string) $row->headline_date,
        'count' => (int) ($row->aggregate ?? 0),
    ])->values();
    $maxDaily = max(1, (int) $dailyRows->max('count'));
    $sourceNames = $sources->mapWithKeys(static fn ($source): array => [(string) $source->id => $source->name])->all();
@endphp
<x-layouts.app :title="'News Crawler'" :meta="$meta">
    <section class="card reveal">
        <div class="split-head">
            <div>
                <h1><i class="fa-solid fa-tower-broadcast"></i> International News Crawler</h1>
                <p class="subtitle">Headlines are normalized, numbers are converted into words, and multi-cipher values are calculated automatically.</p>
            </div>
            <div class="flex gap-2">
                <a class="btn btn-outline btn-sm" href="/admin"><i class="fa-solid fa-arrow-left"></i> Admin</a>
                <a class="btn btn-outline btn-sm" href="/admin/analytics"><i class="fa-solid fa-chart-line"></i> Analytics</a>
            </div>
        </div>
        @if(session('status'))
            <div class="alert alert-success mt-3 text-sm">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-error mt-3 text-sm">{{ $errors->first() }}</div>
        @endif
    </section>

    <section class="card reveal">
        <div class="score-grid">
            <article class="score-card">
                <p><i class="fa-solid fa-database"></i> Total Stored Headlines</p>
                <h2 id="total-headlines">{{ number_format($headlineTotal) }}</h2>
            </article>
            <article class="score-card">
                <p><i class="fa-solid fa-calendar-day"></i> Headlines Today</p>
                <h2 id="headlines-today">{{ number_format($headlinesToday) }}</h2>
            </article>
            <article class="score-card">
                <p><i class="fa-solid fa-satellite-dish"></i> Active Sources</p>
                <h2 id="enabled-sources">{{ number_format($sources->where('enabled', true)->count()) }}</h2>
            </article>
            <article class="score-card">
                <p><i class="fa-solid fa-gears"></i> Latest Run</p>
                <h2 id="run-status">{{ $latestRun?->status ? strtoupper($latestRun->status) : 'NONE' }}</h2>
                <p id="run-stats">
                    @if($latestRun)
                        {{ number_format((int) $latestRun->inserted_count) }} inserted / {{ number_format((int) $latestRun->processed_count) }} processed
                    @else
                        No runs yet.
                    @endif
                </p>
            </article>
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-play"></i> Trigger Crawl Run</h3>
            <p class="small-note">Tip: schedule runs via cron + `php artisan schedule:run` for continuous ingestion.</p>
        </div>
        <form method="POST" action="/admin/news-crawler/run" class="stack-sm mt-3">
            @csrf
            <div class="filters" style="grid-template-columns: 180px auto;">
                <input class="input input-bordered input-sm" type="number" name="limit" min="10" max="500" value="80" placeholder="Limit per source">
                <div class="chips">
                    @foreach($sources as $source)
                        <label class="chip">
                            <input type="checkbox" name="source_ids[]" value="{{ $source->id }}" @checked($source->enabled)>
                            <span>{{ $source->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-bolt"></i> Run now</button>
            </div>
        </form>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-sliders"></i> Source Configuration</h3>
        <form method="POST" action="/admin/news-crawler/sources" class="stack-sm mt-3">
            @csrf
            <div class="table-wrap">
                <table class="table table-zebra">
                    <thead>
                    <tr>
                        <th>Enabled</th>
                        <th>Name</th>
                        <th>RSS URL</th>
                        <th>Locale</th>
                        <th>Weight</th>
                        <th>Last Check</th>
                        <th>Last Error</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($sources as $source)
                        <tr>
                            <td>
                                <input type="hidden" name="sources[{{ $loop->index }}][id]" value="{{ $source->id }}">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input class="toggle toggle-success toggle-sm" type="checkbox" name="enabled_source_ids[]" value="{{ $source->id }}" @checked($source->enabled)>
                                </label>
                            </td>
                            <td>
                                <input class="input input-bordered input-sm" type="text" name="sources[{{ $loop->index }}][name]" value="{{ $source->name }}" required>
                                <input type="hidden" name="sources[{{ $loop->index }}][base_url]" value="{{ $source->base_url }}">
                            </td>
                            <td><input class="input input-bordered input-sm" type="url" name="sources[{{ $loop->index }}][rss_url]" value="{{ $source->rss_url }}"></td>
                            <td><input class="input input-bordered input-sm" type="text" name="sources[{{ $loop->index }}][locale]" value="{{ $source->locale ?? 'en' }}"></td>
                            <td><input class="input input-bordered input-sm" type="number" min="1" max="1000" name="sources[{{ $loop->index }}][weight]" value="{{ $source->weight }}"></td>
                            <td>{{ $source->last_checked_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            <td class="text-xs">{{ $source->last_error ? \Illuminate\Support\Str::limit($source->last_error, 80) : '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="legend-card">
                <h4 class="legend-title"><i class="fa-solid fa-plus"></i> Add New Source</h4>
                <div class="grid md:grid-cols-5 gap-2 mt-2">
                    <input class="input input-bordered input-sm" type="text" name="new_source_name" placeholder="Source name">
                    <input class="input input-bordered input-sm" type="url" name="new_source_base_url" placeholder="Base URL (optional)">
                    <input class="input input-bordered input-sm md:col-span-2" type="url" name="new_source_rss_url" placeholder="RSS / Atom URL">
                    <div class="grid grid-cols-2 gap-2">
                        <input class="input input-bordered input-sm" type="text" name="new_source_locale" placeholder="en" value="en">
                        <input class="input input-bordered input-sm" type="number" min="1" max="1000" name="new_source_weight" value="100">
                    </div>
                </div>
            </div>

            <div>
                <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save sources</button>
            </div>
        </form>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-chart-column"></i> Ingestion Monitoring</h3>
            <span class="small-note">Auto-refresh every 12 seconds.</span>
        </div>
        <div class="news-grid mt-3">
            <article class="legend-card">
                <p class="legend-title"><i class="fa-solid fa-calendar-week"></i> Last 14 Days</p>
                <div id="daily-bars" class="trend-bars mt-2">
                    @foreach($dailyRows as $row)
                        @php
                            $pct = max(2, (int) round(($row['count'] / $maxDaily) * 100));
                        @endphp
                        <div class="trend-row">
                            <span>{{ $row['day'] }}</span>
                            <div class="trend-meter"><div style="width: {{ $pct }}%"></div></div>
                            <span>{{ number_format($row['count']) }}</span>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="legend-card">
                <p class="legend-title"><i class="fa-solid fa-clock"></i> Last 24 Hours</p>
                <div id="hourly-bars" class="trend-bars mt-2"></div>
            </article>

            <article class="legend-card">
                <p class="legend-title"><i class="fa-solid fa-sitemap"></i> Source Throughput (24h)</p>
                <div id="source-bars" class="trend-bars mt-2"></div>
            </article>
        </div>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-newspaper"></i> Latest Captured Headlines</h3>
        <div class="table-wrap mt-2">
            <table class="table table-zebra">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Source</th>
                    <th>Headline</th>
                    <th>English Gematria</th>
                    <th>Captured At</th>
                </tr>
                </thead>
                <tbody id="latest-headline-body">
                    <tr><td colspan="5" class="empty">Loading latest entries...</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <script>
    (() => {
        const statusUrl = '/admin/news-crawler/status';
        const sourceNames = @json($sourceNames);

        const runStatusEl = document.getElementById('run-status');
        const runStatsEl = document.getElementById('run-stats');
        const totalHeadlinesEl = document.getElementById('total-headlines');
        const headlinesTodayEl = document.getElementById('headlines-today');
        const enabledSourcesEl = document.getElementById('enabled-sources');
        const headlineBodyEl = document.getElementById('latest-headline-body');
        const hourlyBarsEl = document.getElementById('hourly-bars');
        const sourceBarsEl = document.getElementById('source-bars');

        const toLocalTime = (iso) => {
            if (!iso) return '-';
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return '-';
            return d.toLocaleString();
        };

        const renderBars = (container, rows) => {
            container.innerHTML = '';
            if (!rows.length) {
                container.innerHTML = '<div class="small-note">No data yet.</div>';
                return;
            }

            const max = Math.max(...rows.map((r) => r.value), 1);
            rows.forEach((row) => {
                const pct = Math.max(2, Math.round((row.value / max) * 100));
                const item = document.createElement('div');
                item.className = 'trend-row';
                item.innerHTML = `
                    <span>${row.label}</span>
                    <div class="trend-meter"><div style="width:${pct}%"></div></div>
                    <span>${row.value.toLocaleString()}</span>
                `;
                container.appendChild(item);
            });
        };

        const renderHeadlines = (rows) => {
            headlineBodyEl.innerHTML = '';
            if (!rows.length) {
                headlineBodyEl.innerHTML = '<tr><td colspan="5" class="empty">No headlines captured yet.</td></tr>';
                return;
            }

            rows.forEach((item) => {
                const tr = document.createElement('tr');
                const idCell = document.createElement('td');
                idCell.textContent = String(item.id ?? '');

                const sourceCell = document.createElement('td');
                sourceCell.textContent = String(item.source ?? 'Unknown');

                const headlineCell = document.createElement('td');
                if (item.url) {
                    const link = document.createElement('a');
                    link.className = 'muted-link';
                    link.href = String(item.url);
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.textContent = String(item.headline ?? '');
                    headlineCell.appendChild(link);
                } else {
                    headlineCell.textContent = String(item.headline ?? '');
                }

                const gemCell = document.createElement('td');
                gemCell.className = 'match-cell';
                gemCell.textContent = Number(item.english_gematria ?? 0).toLocaleString();

                const createdCell = document.createElement('td');
                createdCell.textContent = toLocalTime(item.created_at);

                tr.appendChild(idCell);
                tr.appendChild(sourceCell);
                tr.appendChild(headlineCell);
                tr.appendChild(gemCell);
                tr.appendChild(createdCell);
                headlineBodyEl.appendChild(tr);
            });
        };

        const refresh = async () => {
            try {
                const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();

                if (data.run) {
                    runStatusEl.textContent = String(data.run.status || 'none').toUpperCase();
                    const current = data.run.current_item ? ` | current: ${data.run.current_item}` : '';
                    const phrases = Number(data.run.meta?.phrases_synced || 0).toLocaleString();
                    const words = Number(data.run.meta?.single_words_synced || 0).toLocaleString();
                    runStatsEl.textContent = `${(data.run.inserted_count || 0).toLocaleString()} inserted / ${(data.run.processed_count || 0).toLocaleString()} processed / ${phrases} phrases / ${words} words${current}`;
                }

                if (data.totals) {
                    totalHeadlinesEl.textContent = Number(data.totals.headlines_total || 0).toLocaleString();
                    headlinesTodayEl.textContent = Number(data.totals.headlines_today || 0).toLocaleString();
                    enabledSourcesEl.textContent = Number(data.totals.sources_enabled || 0).toLocaleString();
                }

                const hourlyRows = (data.hourly || []).map((row) => ({
                    label: String(row.hour_label || '').slice(11),
                    value: Number(row.aggregate || 0),
                }));
                renderBars(hourlyBarsEl, hourlyRows);

                const sourceRows = Object.entries(data.by_source_24h || {})
                    .map(([sourceId, value]) => ({
                        label: sourceNames[String(sourceId)] ?? `Source #${sourceId}`,
                        value: Number(value || 0),
                    }))
                    .sort((a, b) => b.value - a.value)
                    .slice(0, 10);
                renderBars(sourceBarsEl, sourceRows);

                renderHeadlines(data.latest_headlines || []);
            } catch (e) {
                // Keep stale content on transient request errors.
            }
        };

        refresh();
        setInterval(refresh, 12000);
    })();
    </script>
</x-layouts.app>
