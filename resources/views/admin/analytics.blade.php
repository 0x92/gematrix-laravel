@php
    $meta = [
        'title' => 'Admin Analytics - Gematrix',
        'robots' => 'noindex,nofollow',
        'canonical' => url('/admin/analytics'),
    ];
@endphp
<x-layouts.app :title="'Admin Analytics'" :meta="$meta">
    <section class="card reveal">
        <div class="split-head">
            <div>
                <h1><i class="fa-solid fa-chart-line"></i> Search Analytics</h1>
                <p class="subtitle">Range: {{ $from->format('Y-m-d') }} to {{ $to->format('Y-m-d H:i') }}</p>
            </div>
            <div class="flex gap-2">
                <a class="btn btn-outline btn-sm" href="/admin"><i class="fa-solid fa-arrow-left"></i> Admin</a>
            </div>
        </div>

        <form method="GET" action="/admin/analytics" class="filters explorer-filters mt-3">
            <select class="select select-bordered select-sm" name="days">
                <option value="7" @selected($days === 7)>Last 7 days</option>
                <option value="14" @selected($days === 14)>Last 14 days</option>
                <option value="30" @selected($days === 30)>Last 30 days</option>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">Apply</button>
        </form>
    </section>

    <section class="card reveal">
        <div class="score-grid">
            <article class="score-card">
                <p><i class="fa-solid fa-magnifying-glass"></i> Total Searches</p>
                <h2>{{ number_format($totalSearches) }}</h2>
                <p>
                    Previous: {{ number_format($previousSearches) }}
                    @if($searchDeltaPercent !== null)
                        ({{ $searchDeltaPercent >= 0 ? '+' : '' }}{{ $searchDeltaPercent }}%)
                    @endif
                </p>
            </article>
            <article class="score-card">
                <p><i class="fa-solid fa-arrow-pointer"></i> Search Clicks</p>
                <h2>{{ number_format($searchClicks) }}</h2>
                <p>Clicks on entry pages from search results.</p>
            </article>
            <article class="score-card">
                <p><i class="fa-solid fa-filter-circle-dollar"></i> Search -> Click CTR</p>
                <h2>{{ number_format($conversionRate, 2) }}%</h2>
                <p>Search clicks divided by total searches.</p>
            </article>
            <article class="score-card">
                <p><i class="fa-solid fa-eye"></i> Total Phrase Views</p>
                <h2>{{ number_format((int) $totalPhraseViews) }}</h2>
                <p>Aggregated view_count across all phrases.</p>
            </article>
        </div>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-wave-square"></i> Daily Search vs Click Trend</h3>
        <div class="grid md:grid-cols-2 gap-4 mt-3">
            <div class="table-wrap">
                <table class="table table-zebra">
                    <thead>
                    <tr>
                        <th>Day</th>
                        <th>Searches</th>
                        <th>Clicks</th>
                        <th>CTR</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($dailySeries as $day)
                        <tr>
                            <td>{{ $day['day'] }}</td>
                            <td>{{ number_format($day['searches']) }}</td>
                            <td>{{ number_format($day['clicks']) }}</td>
                            <td>{{ $day['searches'] > 0 ? number_format(($day['clicks'] / $day['searches']) * 100, 2) : '0.00' }}%</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="space-y-2">
                @foreach($dailySeries as $day)
                    @php
                        $searchWidth = $maxDailySearches > 0 ? min(100, (int) round(($day['searches'] / $maxDailySearches) * 100)) : 0;
                        $clickWidth = $maxDailySearches > 0 ? min(100, (int) round(($day['clicks'] / $maxDailySearches) * 100)) : 0;
                    @endphp
                    <div>
                        <div class="text-xs opacity-70">{{ $day['day'] }}</div>
                        <div class="w-full bg-base-200 rounded px-2 py-1">
                            <div class="h-2 rounded bg-primary" style="width: {{ $searchWidth }}%"></div>
                            <div class="h-2 rounded bg-secondary mt-1" style="width: {{ $clickWidth }}%"></div>
                        </div>
                    </div>
                @endforeach
                <div class="text-xs opacity-70 mt-2">
                    <span class="inline-block w-3 h-3 rounded bg-primary align-middle"></span> Searches
                    <span class="inline-block w-3 h-3 rounded bg-secondary align-middle ml-3"></span> Clicks
                </div>
            </div>
        </div>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-fire"></i> Top Keywords</h3>
        <div class="table-wrap mt-2">
            <table class="table table-zebra">
                <thead>
                <tr>
                    <th>Keyword</th>
                    <th>Searches</th>
                    <th>Clicks</th>
                    <th>CTR</th>
                </tr>
                </thead>
                <tbody>
                @forelse($topKeywords as $item)
                    <tr>
                        <td><a class="muted-link" href="/calculate?q={{ urlencode($item['query']) }}">{{ $item['query'] }}</a></td>
                        <td>{{ number_format($item['searches']) }}</td>
                        <td>{{ number_format($item['clicks']) }}</td>
                        <td>{{ number_format($item['ctr'], 2) }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No keyword data available.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-arrow-trend-up"></i> Rising Keywords (Last 7d vs previous 7d)</h3>
        <div class="table-wrap mt-2">
            <table class="table table-zebra">
                <thead>
                <tr>
                    <th>Keyword</th>
                    <th>Last 7d</th>
                    <th>Prev 7d</th>
                    <th>Delta</th>
                    <th>Growth</th>
                </tr>
                </thead>
                <tbody>
                @forelse($risingKeywords as $item)
                    <tr>
                        <td><a class="muted-link" href="/calculate?q={{ urlencode($item['query']) }}">{{ $item['query'] }}</a></td>
                        <td>{{ number_format($item['recent']) }}</td>
                        <td>{{ number_format($item['previous']) }}</td>
                        <td class="match-cell">+{{ number_format($item['delta']) }}</td>
                        <td>{{ $item['growth'] !== null ? '+' . number_format($item['growth'], 2) . '%' : 'new' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No rising keywords found yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
