<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Phrase;
use App\Models\PhraseClickEvent;
use App\Models\SearchHistory;
use App\Services\AppSettingService;
use App\Services\GematriaCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        private readonly AppSettingService $settingService,
        private readonly GematriaCalculator $calculator,
    ) {
    }

    public function index(): View
    {
        return view('admin.index', [
            'total' => Phrase::count(),
            'approvedCount' => Phrase::query()->where('approved', true)->count(),
            'pending' => Phrase::query()->where('approved', false)->latest()->paginate(20),
            'allCiphers' => config('gematria.ciphers'),
            'enabledCiphers' => $this->settingService->enabledCiphers(),
        ]);
    }

    public function analytics(Request $request): View
    {
        $days = (int) $request->query('days', 14);
        if (! in_array($days, [7, 14, 30], true)) {
            $days = 14;
        }

        $from = now()->startOfDay()->subDays($days - 1);
        $previousFrom = (clone $from)->subDays($days);
        $previousTo = clone $from;

        $totalSearches = SearchHistory::query()
            ->where('created_at', '>=', $from)
            ->count();
        $previousSearches = SearchHistory::query()
            ->where('created_at', '>=', $previousFrom)
            ->where('created_at', '<', $previousTo)
            ->count();

        $searchClicks = PhraseClickEvent::query()
            ->where('source', 'search')
            ->where('created_at', '>=', $from)
            ->count();

        $conversionRate = $totalSearches > 0 ? round(($searchClicks / $totalSearches) * 100, 2) : 0.0;
        $searchDeltaPercent = $previousSearches > 0
            ? round((($totalSearches - $previousSearches) / $previousSearches) * 100, 2)
            : null;

        $topKeywords = SearchHistory::query()
            ->selectRaw('LOWER(query) AS query_norm, COUNT(*) AS aggregate')
            ->where('created_at', '>=', $from)
            ->groupBy('query_norm')
            ->orderByDesc('aggregate')
            ->limit(20)
            ->get();

        $clicksByQuery = PhraseClickEvent::query()
            ->selectRaw('search_query_norm AS query_norm, COUNT(*) AS aggregate')
            ->where('source', 'search')
            ->where('created_at', '>=', $from)
            ->whereNotNull('search_query_norm')
            ->groupBy('query_norm')
            ->pluck('aggregate', 'query_norm')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $topKeywords = $topKeywords->map(function (object $item) use ($clicksByQuery): array {
            $searches = (int) $item->aggregate;
            $clicks = (int) ($clicksByQuery[$item->query_norm] ?? 0);

            return [
                'query' => (string) $item->query_norm,
                'searches' => $searches,
                'clicks' => $clicks,
                'ctr' => $searches > 0 ? round(($clicks / $searches) * 100, 2) : 0.0,
            ];
        })->values();

        $trendRecentFrom = now()->startOfDay()->subDays(6);
        $trendPreviousFrom = (clone $trendRecentFrom)->subDays(7);
        $trendPreviousTo = clone $trendRecentFrom;

        $recentMap = SearchHistory::query()
            ->selectRaw('LOWER(query) AS query_norm, COUNT(*) AS aggregate')
            ->where('created_at', '>=', $trendRecentFrom)
            ->groupBy('query_norm')
            ->pluck('aggregate', 'query_norm')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $previousMap = SearchHistory::query()
            ->selectRaw('LOWER(query) AS query_norm, COUNT(*) AS aggregate')
            ->where('created_at', '>=', $trendPreviousFrom)
            ->where('created_at', '<', $trendPreviousTo)
            ->groupBy('query_norm')
            ->pluck('aggregate', 'query_norm')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $risingKeywords = collect(array_unique(array_merge(array_keys($recentMap), array_keys($previousMap))))
            ->map(function (string $key) use ($recentMap, $previousMap): ?array {
                $recent = (int) ($recentMap[$key] ?? 0);
                $previous = (int) ($previousMap[$key] ?? 0);
                $delta = $recent - $previous;
                if ($recent < 2 || $delta <= 0) {
                    return null;
                }

                return [
                    'query' => $key,
                    'recent' => $recent,
                    'previous' => $previous,
                    'delta' => $delta,
                    'growth' => $previous > 0 ? round(($delta / $previous) * 100, 2) : null,
                ];
            })
            ->filter()
            ->sortBy([
                ['delta', 'desc'],
                ['recent', 'desc'],
            ])
            ->take(12)
            ->values();

        $searchesByDay = SearchHistory::query()
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS aggregate')
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->pluck('aggregate', 'day')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $clicksByDay = PhraseClickEvent::query()
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS aggregate')
            ->where('source', 'search')
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->pluck('aggregate', 'day')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $dailySeries = [];
        $maxDailySearches = 1;
        for ($i = 0; $i < $days; $i++) {
            $day = (clone $from)->addDays($i)->toDateString();
            $searches = (int) ($searchesByDay[$day] ?? 0);
            $clicks = (int) ($clicksByDay[$day] ?? 0);
            $maxDailySearches = max($maxDailySearches, $searches);
            $dailySeries[] = [
                'day' => $day,
                'searches' => $searches,
                'clicks' => $clicks,
            ];
        }

        return view('admin.analytics', [
            'days' => $days,
            'from' => $from,
            'to' => now(),
            'totalSearches' => $totalSearches,
            'previousSearches' => $previousSearches,
            'searchClicks' => $searchClicks,
            'conversionRate' => $conversionRate,
            'searchDeltaPercent' => $searchDeltaPercent,
            'topKeywords' => $topKeywords,
            'risingKeywords' => $risingKeywords,
            'dailySeries' => $dailySeries,
            'maxDailySearches' => $maxDailySearches,
            'totalPhraseViews' => Phrase::query()->sum('view_count'),
        ]);
    }

    public function setCiphers(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled_ciphers' => ['required', 'array', 'min:1'],
            'enabled_ciphers.*' => ['string'],
        ]);

        $this->settingService->setEnabledCiphers($validated['enabled_ciphers']);

        return back();
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $handle = fopen($validated['csv']->getRealPath(), 'r');
        if (! $handle) {
            return back()->withErrors(['csv' => 'CSV could not be opened.']);
        }

        while (($row = fgetcsv($handle)) !== false) {
            $phraseText = trim((string) ($row[0] ?? ''));
            if ($phraseText === '') {
                continue;
            }

            $scores = $this->calculator->calculateAll($phraseText);
            Phrase::query()->updateOrCreate(
                ['phrase' => $phraseText],
                array_merge($scores, ['approved' => false])
            );
        }

        fclose($handle);

        return back();
    }

    public function approve(Phrase $phrase): RedirectResponse
    {
        $phrase->update(['approved' => ! $phrase->approved]);

        return back();
    }
}
