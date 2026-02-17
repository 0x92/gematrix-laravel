<?php

namespace App\Http\Controllers;

use App\Models\Phrase;
use App\Models\PhraseClickEvent;
use App\Models\SearchHistory;
use App\Services\AppSettingService;
use App\Services\GematriaCalculator;
use App\Services\PhraseIngestionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GematriaController extends Controller
{
    public function __construct(
        private readonly GematriaCalculator $calculator,
        private readonly AppSettingService $settingService,
        private readonly PhraseIngestionService $ingestionService,
    ) {
    }

    public function landing(): View
    {
        $totalPhrases = Phrase::query()->where('approved', true)->count();
        $trending = SearchHistory::query()
            ->selectRaw('LOWER(query) as query_norm, COUNT(*) as aggregate')
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('query_norm')
            ->orderByDesc('aggregate')
            ->limit(6)
            ->get();

        return view('gematria.landing', [
            'totalPhrases' => $totalPhrases,
            'trending' => $trending,
            'cipherLabels' => config('gematria.ciphers'),
        ]);
    }

    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', 'matrix code'));
        $query = $query !== '' ? $query : 'matrix code';
        $compareInput = trim((string) $request->query('compare', ''));

        $this->ingestionService->ensurePhraseAndTokens($query);

        $enabledCiphers = $this->settingService->enabledCiphers();
        $selectedCiphers = $this->resolveSelectedCiphers($request, $enabledCiphers);

        $scores = Cache::remember(
            'gematria:calc:'.md5($query.implode(',', $selectedCiphers)),
            now()->addMinutes(30),
            fn (): array => $this->calculator->calculate($query, $selectedCiphers)
        );

        $datasetVersion = Cache::remember(
            'gematria:dataset:version',
            now()->addSeconds(30),
            static function (): string {
                $row = DB::table('phrases')
                    ->selectRaw('COUNT(*) AS cnt, COALESCE(MAX(id), 0) AS max_id')
                    ->first();

                return (string) (($row->cnt ?? 0).':'.($row->max_id ?? 0));
            }
        );

        if ($request->query('q')) {
            SearchHistory::query()->create([
                'user_id' => $request->user()?->id,
                'query' => $query,
                'locale' => app()->getLocale(),
                'ciphers' => $selectedCiphers,
            ]);
        }

        $matches = Cache::remember(
            'gematria:matches:'.md5($datasetVersion.'|'.$query.'|'.json_encode($scores)),
            now()->addMinutes(10),
            fn () => $this->buildMatches($query, $scores)
        );

        $favorites = $request->user()
            ? $request->user()->favorites()->pluck('phrase')->all()
            : [];

        $latest = Phrase::query()->where('approved', true)->latest()->take(12)->get();
        $exactMatchEntry = Phrase::query()
            ->where('approved', true)
            ->whereRaw('LOWER(phrase) = ?', [mb_strtolower($query)])
            ->orderBy('id')
            ->first();
        $trending = SearchHistory::query()
            ->selectRaw('LOWER(query) as query_norm, COUNT(*) as aggregate')
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('query_norm')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->get();
        $subphraseDecode = $this->buildSubphraseDecode($query, $selectedCiphers);
        $anomalies = $this->buildAnomalySignals($scores);
        $trendLens = $this->buildTrendLens($query);
        $themeInsights = $this->buildThemeInsights($scores);
        $compareRows = $this->buildCompareRows($compareInput !== '' ? $compareInput : $query, $selectedCiphers);
        $analysisId = substr(hash('sha256', strtolower($query).'|'.implode(',', $selectedCiphers).'|'.date('Y-m-d')), 0, 16);
        $datasetFreshness = Phrase::query()->where('approved', true)->max('updated_at');
        $recentQueries = SearchHistory::query()
            ->selectRaw('LOWER(query) as query_norm, MAX(created_at) as seen_at, COUNT(*) as aggregate')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('query_norm')
            ->orderByDesc('seen_at')
            ->limit(10)
            ->get();
        $heatmap = $this->buildCipherHeatmap($matches, array_keys($scores));

        return view('gematria.index', [
            'query' => $query,
            'scores' => $scores,
            'matches' => $matches,
            'cipherLabels' => config('gematria.ciphers'),
            'cipherIcons' => config('gematria.icons'),
            'cipherDescriptions' => config('gematria.descriptions'),
            'enabledCiphers' => $enabledCiphers,
            'selectedCiphers' => $selectedCiphers,
            'favorites' => $favorites,
            'totalPhrases' => Phrase::query()->where('approved', true)->count(),
            'latest' => $latest,
            'latestCount' => $latest->count(),
            'exactMatchEntry' => $exactMatchEntry,
            'trending' => $trending,
            'subphraseDecode' => $subphraseDecode,
            'anomalies' => $anomalies,
            'trendLens' => $trendLens,
            'themeInsights' => $themeInsights,
            'compareRows' => $compareRows,
            'compareInput' => $compareInput,
            'analysisId' => $analysisId,
            'datasetFreshness' => $datasetFreshness,
            'recentQueries' => $recentQueries,
            'heatmap' => $heatmap,
        ]);
    }

    public function explorer(Request $request): View
    {
        $cipher = (string) $request->query('cipher', 'english_gematria');
        $value = $request->query('value');
        $phrase = trim((string) $request->query('phrase', ''));
        $theme = (string) $request->query('theme', '');
        $minViews = max(0, (int) $request->query('min_views', 0));
        $minSearches = max(0, (int) $request->query('min_searches', 0));
        $sort = (string) $request->query('sort', 'created_at');
        $dir = (string) $request->query('dir', 'desc');

        $allowedSort = array_merge(['created_at', 'phrase'], array_keys(config('gematria.ciphers')));
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        $query = Phrase::query()
            ->leftJoinSub(
                SearchHistory::query()
                    ->selectRaw('LOWER(query) as query_norm, COUNT(*) as aggregate')
                    ->groupBy('query_norm'),
                'search_stats',
                static fn ($join): mixed => $join->on(DB::raw('LOWER(phrases.phrase)'), '=', 'search_stats.query_norm')
            )
            ->where('phrases.approved', true);
        if ($phrase !== '') {
            $query->where('phrases.phrase', 'like', '%'.$phrase.'%');
        }
        if ($theme !== '') {
            $themeKeywords = config('gematria.theme_keywords.'.$theme, []);
            if (is_array($themeKeywords) && $themeKeywords !== []) {
                $query->where(function ($builder) use ($themeKeywords): void {
                    foreach ($themeKeywords as $keyword) {
                        $builder->orWhere('phrases.phrase', 'like', '%'.$keyword.'%');
                    }
                });
            }
        }

        if (array_key_exists($cipher, config('gematria.ciphers')) && is_numeric($value)) {
            $query->where("phrases.{$cipher}", (int) $value);
        }
        if ($minViews > 0) {
            $query->where('phrases.view_count', '>=', $minViews);
        }
        if ($minSearches > 0) {
            $query->whereRaw('COALESCE(search_stats.aggregate, 0) >= ?', [$minSearches]);
        }

        $perPage = (int) $request->query('per_page', 25);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;

        $phrases = $query
            ->select('phrases.*')
            ->selectRaw('COALESCE(search_stats.aggregate, 0) as search_hits')
            ->orderBy('phrases.'.$sort, $dir)
            ->paginate($perPage)
            ->withQueryString();
        $phraseQueries = $phrases->getCollection()
            ->pluck('phrase')
            ->map(static fn (string $text): string => mb_strtolower($text))
            ->unique()
            ->values()
            ->all();
        $searchCounts = $this->fetchSearchCounts($phraseQueries);

        return view('gematria.explorer', [
            'phrases' => $phrases,
            'cipherLabels' => config('gematria.ciphers'),
            'searchCounts' => $searchCounts,
            'filters' => [
                'cipher' => $cipher,
                'value' => $value,
                'phrase' => $phrase,
                'theme' => $theme,
                'min_views' => $minViews,
                'min_searches' => $minSearches,
                'sort' => $sort,
                'dir' => $dir,
                'per_page' => $perPage,
            ],
            'themes' => config('gematria.themes', []),
            'analysisId' => substr(hash('sha256', strtolower($phrase).'|'.$theme.'|'.$cipher.'|'.$value.'|'.$sort.'|'.$dir.'|'.$perPage.'|'.$minViews.'|'.$minSearches), 0, 16),
            'datasetFreshness' => Phrase::query()->where('approved', true)->max('updated_at'),
        ]);
    }

    public function show(Phrase $phrase, Request $request): View
    {
        abort_unless($phrase->approved, 404);
        Phrase::query()->whereKey($phrase->id)->increment('view_count');
        $source = $this->resolveClickSource((string) $request->query('source', 'direct'));
        $searchQuery = trim((string) $request->query('search_query', ''));
        $searchQuery = $searchQuery !== '' ? mb_substr($searchQuery, 0, 200) : null;
        PhraseClickEvent::query()->create([
            'phrase_id' => $phrase->id,
            'user_id' => $request->user()?->id,
            'source' => $source,
            'search_query' => $searchQuery,
            'search_query_norm' => $searchQuery ? mb_strtolower($searchQuery) : null,
        ]);
        $phrase->refresh();

        $cipher = (string) $request->query('cipher', 'english_gematria');
        if (! array_key_exists($cipher, config('gematria.ciphers'))) {
            $cipher = 'english_gematria';
        }

        $value = (int) $phrase->{$cipher};
        $allCiphers = array_keys(config('gematria.ciphers'));
        $otherCiphers = array_values(array_filter($allCiphers, static fn (string $key): bool => $key !== $cipher));
        $rankMode = $this->resolveRankMode((string) $request->query('rank_mode', 'balanced'));
        $rankModeLabels = [
            'balanced' => 'Balanced',
            'popular' => 'Most Popular',
            'symbolic' => 'Most Symbolic',
            'rare_strong' => 'Rare & Strong',
        ];

        $sharedScoreParts = [];
        $sharedBindings = [];
        foreach ($otherCiphers as $otherCipher) {
            $sharedScoreParts[] = "CASE WHEN phrases.{$otherCipher} = ? THEN 1 ELSE 0 END";
            $sharedBindings[] = (int) $phrase->{$otherCipher};
        }
        $sharedScoreExpression = $sharedScoreParts !== [] ? implode(' + ', $sharedScoreParts) : '0';
        $scoreExpressions = [
            'balanced' => "((({$sharedScoreExpression}) * 100) + (LN(COALESCE(search_stats.aggregate, 0) + 1) * 20) + (LN(phrases.view_count + 1) * 12) + (CASE WHEN CHAR_LENGTH(phrases.phrase) BETWEEN 4 AND 18 THEN 8 ELSE 0 END))",
            'popular' => "((({$sharedScoreExpression}) * 70) + (LN(COALESCE(search_stats.aggregate, 0) + 1) * 40) + (LN(phrases.view_count + 1) * 20))",
            'symbolic' => "((({$sharedScoreExpression}) * 130) + (CASE WHEN CHAR_LENGTH(phrases.phrase) BETWEEN 4 AND 14 THEN 12 ELSE 0 END) + (CASE WHEN POSITION(' ' IN phrases.phrase) > 0 THEN 6 ELSE 0 END) + (LN(COALESCE(search_stats.aggregate, 0) + 1) * 12))",
            'rare_strong' => "((({$sharedScoreExpression}) * 120) + (300.0 / (COALESCE(search_stats.aggregate, 0) + 1)) + (80.0 / (phrases.view_count + 1)) + (CASE WHEN CHAR_LENGTH(phrases.phrase) <= 12 THEN 8 ELSE 0 END))",
        ];
        $scoreExpression = $scoreExpressions[$rankMode] ?? $scoreExpressions['balanced'];

        $perPage = (int) $request->query('per_page', 250);
        $perPage = max(50, min($perPage, 1000));

        $connections = Phrase::query()
            ->leftJoinSub(
                SearchHistory::query()
                    ->selectRaw('LOWER(query) as query_norm, COUNT(*) as aggregate')
                    ->groupBy('query_norm'),
                'search_stats',
                static fn ($join): mixed => $join->on(DB::raw('LOWER(phrases.phrase)'), '=', 'search_stats.query_norm')
            )
            ->where('phrases.approved', true)
            ->where('phrases.id', '!=', $phrase->id)
            ->where("phrases.{$cipher}", $value)
            ->select('phrases.*')
            ->selectRaw("({$sharedScoreExpression}) as shared_cipher_hits", $sharedBindings)
            ->selectRaw('COALESCE(search_stats.aggregate, 0) as search_hits')
            ->selectRaw("{$scoreExpression} as interesting_score", $sharedBindings)
            ->orderByDesc('interesting_score')
            ->orderByDesc('shared_cipher_hits')
            ->orderByDesc('search_hits')
            ->orderByDesc('phrases.view_count')
            ->orderBy('phrases.phrase')
            ->paginate($perPage)
            ->withQueryString();
        $connections->getCollection()->transform(function (Phrase $item) use ($rankMode, $allCiphers, $phrase): Phrase {
            $shared = (int) ($item->shared_cipher_hits ?? 0);
            $search = (int) ($item->search_hits ?? 0);
            $views = (int) $item->view_count;
            $lengthBonus = mb_strlen($item->phrase) >= 4 && mb_strlen($item->phrase) <= 18 ? 1 : 0;
            $modeHint = match ($rankMode) {
                'popular' => 'popularity weighted',
                'symbolic' => 'symbolic overlap weighted',
                'rare_strong' => 'rarity weighted',
                default => 'balanced profile',
            };
            $item->score_explain = sprintf(
                '%d shared ciphers, %d searches, %d views, length bonus %s (%s)',
                $shared,
                $search,
                $views,
                $lengthBonus ? 'on' : 'off',
                $modeHint
            );
            $item->theme = $this->resolveThemeForPhrase($item->phrase);
            $matchedCiphers = [];
            foreach ($allCiphers as $cipherKey) {
                if ((int) ($item->{$cipherKey} ?? -1) === (int) ($phrase->{$cipherKey} ?? -2)) {
                    $matchedCiphers[] = $cipherKey;
                }
            }
            $item->matched_ciphers = $matchedCiphers;
            $item->confidence_score = round(min(100, ($shared * 12) + (count($matchedCiphers) * 5) + min(30, (int) (log($search + 1) * 8)) + min(20, (int) (log($views + 1) * 6))), 2);

            return $item;
        });

        $entrySearchCountMap = $this->fetchSearchCounts([
            mb_strtolower($phrase->phrase),
        ]);

        $allTotals = $phrase->only(array_keys(config('gematria.ciphers')));
        $letterChars = $this->calculator->extractLetterChars($phrase->phrase);
        $selectedBreakdown = $this->buildBreakdown($letterChars, $cipher);
        $selectedCipherMap = $this->calculator->getCipherMap($cipher);
        $graphData = $this->buildPatternGraph($phrase, $connections->getCollection()->take(18)->all(), $allCiphers, $cipher);

        return view('gematria.show', [
            'entry' => $phrase,
            'cipher' => $cipher,
            'value' => $value,
            'connections' => $connections,
            'cipherLabels' => config('gematria.ciphers'),
            'cipherDescriptions' => config('gematria.descriptions'),
            'cipherDetails' => config('gematria.details'),
            'entrySearchCount' => (int) ($entrySearchCountMap[mb_strtolower($phrase->phrase)] ?? 0),
            'allTotals' => $allTotals,
            'selectedBreakdown' => $selectedBreakdown,
            'selectedCipherMap' => $selectedCipherMap,
            'rankMode' => $rankMode,
            'rankModeLabels' => $rankModeLabels,
            'connectionPerPage' => $perPage,
            'graphData' => $graphData,
            'analysisId' => substr(hash('sha256', strtolower($phrase->phrase).'|'.$cipher.'|'.$rankMode.'|'.$perPage), 0, 16),
            'datasetFreshness' => Phrase::query()->where('approved', true)->max('updated_at'),
        ]);
    }

    public function random(): RedirectResponse
    {
        $id = Phrase::query()->where('approved', true)->inRandomOrder()->value('id');

        if (! $id) {
            return redirect('/calculate');
        }

        return redirect('/entry/'.$id.'?source=random');
    }

    /**
     * @param array<string, int> $scores
     * @return array<int, array<string, mixed>>
     */
    private function buildMatches(string $query, array $scores): array
    {
        if ($scores === []) {
            return [];
        }

        $candidateIds = [];
        foreach ($scores as $column => $value) {
            $ids = Cache::remember(
                "gematria:ids:{$column}:{$value}",
                now()->addMinutes(10),
                static fn () => Phrase::query()
                    ->where('approved', true)
                    ->where($column, $value)
                    ->orderBy('phrase')
                    ->limit(160)
                    ->pluck('id')
                    ->all()
            );

            foreach ($ids as $id) {
                $candidateIds[$id] = true;
            }
        }

        if ($candidateIds === []) {
            return [];
        }

        return Phrase::query()
            ->whereIn('id', array_keys($candidateIds))
            ->where('approved', true)
            ->where('phrase', '!=', $query)
            ->orderBy('english_gematria')
            ->limit(80)
            ->get()
            ->map(function (Phrase $phrase) use ($scores): array {
                $hitColumns = [];

                foreach ($scores as $column => $value) {
                    if ((int) $phrase->{$column} === $value) {
                        $hitColumns[] = $column;
                    }
                }

                return [
                    'id' => $phrase->id,
                    'phrase' => $phrase->phrase,
                    'scores' => $phrase->only(array_keys(config('gematria.ciphers'))),
                    'hits' => $hitColumns,
                    'confidence_score' => round((count($hitColumns) / max(1, count($scores))) * 100, 2),
                ];
            })
            ->sortByDesc(static fn (array $row): int => count($row['hits']))
            ->take(40)
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $enabledCiphers
     * @return array<int, string>
     */
    private function resolveSelectedCiphers(Request $request, array $enabledCiphers): array
    {
        $requested = $request->query('ciphers');

        if (! is_array($requested) || $requested === []) {
            $preferred = $request->user()?->preferred_ciphers ?: $enabledCiphers;

            return array_values(array_intersect($enabledCiphers, $preferred));
        }

        $safe = [];
        foreach ($requested as $cipher) {
            if (is_string($cipher) && in_array($cipher, $enabledCiphers, true)) {
                $safe[] = $cipher;
            }
        }

        return $safe !== [] ? $safe : $enabledCiphers;
    }

    /**
     * @param array<int, string> $letters
     * @return array{items: array<int, array{char: string, index: int, value: int}>, expression: string}
     */
    private function buildBreakdown(array $letters, string $cipher): array
    {
        $map = $this->calculator->getCipherMap($cipher);
        $items = [];

        foreach ($letters as $char) {
            $index = ord($char) - 64;
            $items[] = [
                'char' => $char,
                'index' => $index,
                'value' => $map[$index] ?? 0,
            ];
        }

        $expression = implode(' + ', array_map(static fn (array $item): string => (string) $item['value'], $items));

        return [
            'items' => $items,
            'expression' => $expression,
        ];
    }

    /**
     * @param array<int, string> $queries
     * @return array<string, int>
     */
    private function fetchSearchCounts(array $queries): array
    {
        if ($queries === []) {
            return [];
        }

        return SearchHistory::query()
            ->selectRaw('LOWER(query) as query_norm, COUNT(*) as aggregate')
            ->where(function ($builder) use ($queries): void {
                foreach ($queries as $query) {
                    $builder->orWhereRaw('LOWER(query) = ?', [$query]);
                }
            })
            ->groupBy('query_norm')
            ->pluck('aggregate', 'query_norm')
            ->map(static fn ($value): int => (int) $value)
            ->all();
    }

    private function resolveClickSource(string $source): string
    {
        $safe = strtolower(trim($source));
        $allowed = ['direct', 'search', 'explorer', 'connection', 'latest', 'random', 'workspace', 'graph', 'subphrase', 'theme', 'hybrid', 'compare'];

        return in_array($safe, $allowed, true) ? $safe : 'direct';
    }

    private function resolveRankMode(string $rankMode): string
    {
        $safe = strtolower(trim($rankMode));
        $allowed = ['balanced', 'popular', 'symbolic', 'rare_strong'];

        return in_array($safe, $allowed, true) ? $safe : 'balanced';
    }

    /**
     * @param array<int, string> $selectedCiphers
     * @return array<int, array<string, mixed>>
     */
    private function buildSubphraseDecode(string $query, array $selectedCiphers): array
    {
        $tokens = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim($query))) ?: [], static fn (string $t): bool => $t !== ''));
        if ($tokens === []) {
            return [];
        }

        $ngrams = [];
        $maxN = min(3, count($tokens));
        for ($n = 1; $n <= $maxN; $n++) {
            for ($i = 0; $i <= count($tokens) - $n; $i++) {
                $chunk = implode(' ', array_slice($tokens, $i, $n));
                if (mb_strlen($chunk) >= 2) {
                    $ngrams[$chunk] = true;
                }
            }
        }

        $rows = [];
        foreach (array_keys($ngrams) as $gram) {
            $scores = $this->calculator->calculate($gram, $selectedCiphers);
            $english = (int) ($scores['english_gematria'] ?? 0);
            $simple = (int) ($scores['simple_gematria'] ?? 0);
            $matches = Phrase::query()
                ->where('approved', true)
                ->where(static function ($builder) use ($english, $simple): void {
                    $builder->where('english_gematria', $english)
                        ->orWhere('simple_gematria', $simple);
                })
                ->count();
            $exact = Phrase::query()
                ->where('approved', true)
                ->whereRaw('LOWER(phrase) = ?', [mb_strtolower($gram)])
                ->value('id');

            $rows[] = [
                'text' => $gram,
                'scores' => $scores,
                'matches' => $matches,
                'exact_id' => $exact ? (int) $exact : null,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return [$b['matches'], mb_strlen($b['text'])] <=> [$a['matches'], mb_strlen($a['text'])];
        });

        return array_slice($rows, 0, 18);
    }

    /**
     * @param array<string, int> $scores
     * @return array<int, array<string, mixed>>
     */
    private function buildAnomalySignals(array $scores): array
    {
        $signals = [];
        foreach ($scores as $cipher => $value) {
            $frequency = Phrase::query()
                ->where('approved', true)
                ->where($cipher, (int) $value)
                ->count();
            $rarityScore = round(100 / max(1, $frequency), 2);
            $signals[] = [
                'cipher' => $cipher,
                'value' => (int) $value,
                'frequency' => $frequency,
                'rarity_score' => $rarityScore,
                'anomaly_level' => $frequency <= 5 ? 'extreme' : ($frequency <= 20 ? 'high' : ($frequency <= 80 ? 'medium' : 'low')),
            ];
        }

        usort($signals, static fn (array $a, array $b): int => $b['rarity_score'] <=> $a['rarity_score']);

        return array_slice($signals, 0, 10);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTrendLens(string $query): array
    {
        $rows = SearchHistory::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->whereRaw('LOWER(query) = ?', [mb_strtolower($query)])
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $daily = $rows->map(static fn ($row): array => [
            'day' => (string) $row->day,
            'aggregate' => (int) $row->aggregate,
        ])->all();
        $current7 = SearchHistory::query()
            ->whereRaw('LOWER(query) = ?', [mb_strtolower($query)])
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $previous7 = SearchHistory::query()
            ->whereRaw('LOWER(query) = ?', [mb_strtolower($query)])
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->count();
        $spike = $previous7 > 0 ? round((($current7 - $previous7) / $previous7) * 100, 2) : null;

        return [
            'daily' => $daily,
            'current7' => $current7,
            'previous7' => $previous7,
            'spike_percent' => $spike,
            'max_daily' => max(1, (int) max(array_column($daily, 'aggregate') ?: [0])),
        ];
    }

    /**
     * @param array<string, int> $scores
     * @return array<int, array<string, mixed>>
     */
    private function buildThemeInsights(array $scores): array
    {
        $themes = config('gematria.themes', []);
        $result = [];
        foreach ($themes as $key => $label) {
            $keywords = config('gematria.theme_keywords.'.$key, []);
            if (! is_array($keywords) || $keywords === []) {
                continue;
            }

            $query = Phrase::query()->where('approved', true)->where(function ($builder) use ($keywords): void {
                foreach ($keywords as $keyword) {
                    $builder->orWhere('phrase', 'like', '%'.$keyword.'%');
                }
            });

            $query->where(function ($builder) use ($scores): void {
                foreach ($scores as $cipher => $value) {
                    $builder->orWhere($cipher, (int) $value);
                }
            });

            $top = (clone $query)->orderByDesc('view_count')->limit(5)->get(['id', 'phrase', 'view_count']);
            $result[] = [
                'key' => (string) $key,
                'label' => (string) $label,
                'count' => (clone $query)->count(),
                'top' => $top,
            ];
        }

        usort($result, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $result;
    }

    /**
     * @param array<int, Phrase> $items
     * @param array<int, string> $allCiphers
     * @return array<string, mixed>
     */
    private function buildPatternGraph(Phrase $entry, array $items, array $allCiphers, string $selectedCipher): array
    {
        $nodes = [[
            'id' => 'root-'.$entry->id,
            'label' => $entry->phrase,
            'url' => '/entry/'.$entry->id.'?cipher='.$selectedCipher,
            'is_root' => true,
            'score' => 10,
        ]];
        $edges = [];

        foreach ($items as $item) {
            $nodes[] = [
                'id' => 'node-'.$item->id,
                'label' => $item->phrase,
                'url' => '/entry/'.$item->id.'?cipher='.$selectedCipher.'&source=graph&search_query='.urlencode($entry->phrase),
                'is_root' => false,
                'score' => (int) max(1, ($item->shared_cipher_hits ?? 0)),
            ];
            $edges[] = [
                'source' => 'root-'.$entry->id,
                'target' => 'node-'.$item->id,
                'weight' => (int) max(1, ($item->shared_cipher_hits ?? 0)),
            ];
        }

        $pairEdges = [];
        for ($i = 0; $i < count($items); $i++) {
            for ($j = $i + 1; $j < count($items); $j++) {
                $shared = 0;
                foreach ($allCiphers as $cipher) {
                    if ((int) $items[$i]->{$cipher} === (int) $items[$j]->{$cipher}) {
                        $shared++;
                    }
                }
                if ($shared >= 2) {
                    $pairEdges[] = [
                        'source' => 'node-'.$items[$i]->id,
                        'target' => 'node-'.$items[$j]->id,
                        'weight' => $shared,
                    ];
                }
            }
        }
        usort($pairEdges, static fn (array $a, array $b): int => $b['weight'] <=> $a['weight']);
        $edges = array_merge($edges, array_slice($pairEdges, 0, 36));

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'meta' => [
                'node_count' => count($nodes),
                'edge_count' => count($edges),
                'pair_edge_count' => count($pairEdges),
            ],
        ];
    }

    private function resolveThemeForPhrase(string $phrase): string
    {
        $text = mb_strtolower($phrase);
        $themes = config('gematria.themes', []);
        foreach ($themes as $key => $label) {
            $keywords = config('gematria.theme_keywords.'.$key, []);
            if (! is_array($keywords)) {
                continue;
            }
            foreach ($keywords as $keyword) {
                if (str_contains($text, (string) $keyword)) {
                    return (string) $label;
                }
            }
        }

        return 'General';
    }

    /**
     * @param array<int, string> $selectedCiphers
     * @return array<int, array<string, mixed>>
     */
    private function buildCompareRows(string $rawInput, array $selectedCiphers): array
    {
        $phrases = array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/[\r\n,;]+/u', $rawInput) ?: []
        ), static fn (string $line): bool => $line !== '')));

        $phrases = array_slice($phrases, 0, 8);
        $rows = [];
        foreach ($phrases as $text) {
            $scores = $this->calculator->calculate($text, $selectedCiphers);
            $entryId = Phrase::query()
                ->where('approved', true)
                ->whereRaw('LOWER(phrase) = ?', [mb_strtolower($text)])
                ->value('id');

            $rows[] = [
                'phrase' => $text,
                'scores' => $scores,
                'entry_id' => $entryId ? (int) $entryId : null,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $matches
     * @param array<int, string> $selectedCiphers
     * @return array<int, array<string, mixed>>
     */
    private function buildCipherHeatmap(array $matches, array $selectedCiphers): array
    {
        $rows = [];
        foreach ($selectedCiphers as $left) {
            foreach ($selectedCiphers as $right) {
                if ($left >= $right) {
                    continue;
                }
                $count = 0;
                foreach ($matches as $match) {
                    $hits = $match['hits'] ?? [];
                    if (in_array($left, $hits, true) && in_array($right, $hits, true)) {
                        $count++;
                    }
                }
                $rows[] = [
                    'left' => $left,
                    'right' => $right,
                    'count' => $count,
                ];
            }
        }

        usort($rows, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($rows, 0, 20);
    }
}
