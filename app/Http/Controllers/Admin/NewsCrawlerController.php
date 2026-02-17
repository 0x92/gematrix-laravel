<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrawlerRun;
use App\Models\NewsHeadline;
use App\Models\NewsSource;
use App\Services\NewsCrawlerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsCrawlerController extends Controller
{
    public function __construct(private readonly NewsCrawlerService $crawlerService)
    {
    }

    public function index(): View
    {
        $this->crawlerService->ensureDefaultSources();

        $sources = NewsSource::query()->orderByDesc('enabled')->orderBy('name')->get();
        $latestRun = CrawlerRun::query()->where('crawler_name', NewsCrawlerService::CRAWLER_NAME)->latest('id')->first();

        $daily = NewsHeadline::query()
            ->selectRaw('headline_date, COUNT(*) as aggregate')
            ->where('headline_date', '>=', now()->subDays(14)->toDateString())
            ->groupBy('headline_date')
            ->orderBy('headline_date')
            ->get();

        $bySource = NewsHeadline::query()
            ->selectRaw('news_source_id, COUNT(*) as aggregate')
            ->groupBy('news_source_id')
            ->pluck('aggregate', 'news_source_id')
            ->map(static fn ($v): int => (int) $v)
            ->all();

        return view('admin.news_crawler', [
            'sources' => $sources,
            'latestRun' => $latestRun,
            'headlineTotal' => NewsHeadline::query()->count(),
            'headlinesToday' => NewsHeadline::query()->whereDate('headline_date', now()->toDateString())->count(),
            'daily' => $daily,
            'bySource' => $bySource,
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_ids' => ['array'],
            'source_ids.*' => ['integer'],
            'limit' => ['nullable', 'integer', 'min:10', 'max:500'],
        ]);

        $sourceIds = array_map(static fn ($v): int => (int) $v, $validated['source_ids'] ?? []);
        $limit = (int) ($validated['limit'] ?? 80);
        $run = $this->crawlerService->run($sourceIds !== [] ? $sourceIds : null, $limit);
        $phrasesSynced = (int) data_get($run->meta, 'phrases_synced', 0);
        $wordsSynced = (int) data_get($run->meta, 'single_words_synced', 0);

        return back()->with('status', 'Crawler run #'.$run->id.' finished: '.$run->inserted_count.' new headlines, '.$phrasesSynced.' phrases synced, '.$wordsSynced.' words synced.');
    }

    public function saveSources(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sources' => ['nullable', 'array'],
            'sources.*.id' => ['required_with:sources', 'integer', 'exists:news_sources,id'],
            'sources.*.name' => ['required_with:sources', 'string', 'max:255'],
            'sources.*.base_url' => ['nullable', 'url', 'max:255'],
            'sources.*.rss_url' => ['nullable', 'url', 'max:500'],
            'sources.*.locale' => ['nullable', 'string', 'max:10'],
            'sources.*.weight' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'enabled_source_ids' => ['nullable', 'array'],
            'enabled_source_ids.*' => ['integer', 'exists:news_sources,id'],
            'new_source_name' => ['nullable', 'string', 'max:255'],
            'new_source_base_url' => ['nullable', 'url', 'max:255'],
            'new_source_rss_url' => ['nullable', 'url', 'max:500'],
            'new_source_locale' => ['nullable', 'string', 'max:10'],
            'new_source_weight' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $enabledSourceIds = array_map(static fn ($v): int => (int) $v, $validated['enabled_source_ids'] ?? []);

        foreach (($validated['sources'] ?? []) as $row) {
            $sourceId = (int) $row['id'];
            NewsSource::query()->whereKey((int) $row['id'])->update([
                'name' => (string) $row['name'],
                'base_url' => $row['base_url'] ?? null,
                'rss_url' => $row['rss_url'] ?? null,
                'locale' => $row['locale'] ?? 'en',
                'enabled' => in_array($sourceId, $enabledSourceIds, true),
                'weight' => (int) ($row['weight'] ?? 100),
            ]);
        }

        if (! empty($validated['new_source_name']) && ! empty($validated['new_source_rss_url'])) {
            NewsSource::query()->create([
                'name' => (string) $validated['new_source_name'],
                'base_url' => $validated['new_source_base_url'] ?? null,
                'rss_url' => (string) $validated['new_source_rss_url'],
                'locale' => $validated['new_source_locale'] ?? 'en',
                'enabled' => true,
                'weight' => (int) ($validated['new_source_weight'] ?? 100),
            ]);
        }

        return back()->with('status', 'News sources updated.');
    }

    public function status(): JsonResponse
    {
        $run = CrawlerRun::query()->where('crawler_name', NewsCrawlerService::CRAWLER_NAME)->latest('id')->first();

        $latestHeadlines = NewsHeadline::query()
            ->with('source:id,name')
            ->latest('id')
            ->take(20)
            ->get()
            ->map(static function (NewsHeadline $item): array {
                return [
                    'id' => $item->id,
                    'headline' => $item->headline,
                    'source' => $item->source?->name,
                    'url' => $item->url,
                    'english_gematria' => (int) $item->english_gematria,
                    'created_at' => optional($item->created_at)?->toIso8601String(),
                ];
            })
            ->values();

        $hourly = NewsHeadline::query()
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD HH24:00') as hour_label, COUNT(*) as aggregate")
            ->where('created_at', '>=', now()->subHours(24))
            ->groupBy('hour_label')
            ->orderBy('hour_label')
            ->get();

        $bySource24h = NewsHeadline::query()
            ->selectRaw('news_source_id, COUNT(*) as aggregate')
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('news_source_id')
            ->pluck('aggregate', 'news_source_id')
            ->map(static fn ($v): int => (int) $v)
            ->all();

        return response()->json([
            'run' => $run ? [
                'id' => $run->id,
                'status' => $run->status,
                'current_item' => $run->current_item,
                'processed_count' => (int) $run->processed_count,
                'inserted_count' => (int) $run->inserted_count,
                'started_at' => optional($run->started_at)?->toIso8601String(),
                'finished_at' => optional($run->finished_at)?->toIso8601String(),
                'error_message' => $run->error_message,
                'meta' => is_array($run->meta) ? $run->meta : [],
            ] : null,
            'totals' => [
                'headlines_total' => NewsHeadline::query()->count(),
                'headlines_today' => NewsHeadline::query()->whereDate('headline_date', now()->toDateString())->count(),
                'sources_enabled' => NewsSource::query()->where('enabled', true)->count(),
            ],
            'latest_headlines' => $latestHeadlines,
            'hourly' => $hourly,
            'by_source_24h' => $bySource24h,
        ]);
    }
}
