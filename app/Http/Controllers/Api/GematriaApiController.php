<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Phrase;
use App\Services\AppSettingService;
use App\Services\GematriaCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GematriaApiController extends Controller
{
    public function __construct(
        private readonly GematriaCalculator $calculator,
        private readonly AppSettingService $settingService,
    ) {
    }

    public function calculate(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', 'matrix code'));
        $q = $q !== '' ? $q : 'matrix code';

        $enabled = $this->settingService->enabledCiphers();
        $requested = $request->query('ciphers', $enabled);
        $selected = is_array($requested) ? array_values(array_intersect($enabled, $requested)) : $enabled;

        $scores = Cache::remember(
            'api:calc:'.md5($q.implode(',', $selected)),
            now()->addMinutes(30),
            fn (): array => $this->calculator->calculate($q, $selected)
        );

        return response()->json([
            'query' => $q,
            'scores' => $scores,
            'ciphers' => config('gematria.ciphers'),
        ]);
    }

    public function explorer(Request $request): JsonResponse
    {
        $cipher = (string) $request->query('cipher', 'english_ordinal');
        $value = $request->query('value');

        $phrases = Phrase::query()
            ->where('approved', true)
            ->when(is_numeric($value) && array_key_exists($cipher, config('gematria.ciphers')), function ($query) use ($cipher, $value): void {
                $query->where($cipher, (int) $value);
            })
            ->when($request->filled('phrase'), function ($query) use ($request): void {
                $query->where('phrase', 'like', '%'.trim((string) $request->query('phrase')).'%');
            })
            ->limit(50)
            ->get();

        return response()->json($phrases);
    }

    public function suggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $suggestions = Phrase::query()
            ->where('approved', true)
            ->where('phrase', 'like', $q.'%')
            ->limit(8)
            ->pluck('phrase');

        return response()->json($suggestions);
    }

    public function hybridSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([
                'query' => $q,
                'results' => [],
                'message' => 'Query must contain at least 2 characters.',
            ], 422);
        }

        $limit = max(5, min(50, (int) $request->query('limit', 20)));
        $enabled = $this->settingService->enabledCiphers();
        $datasetVersion = Cache::remember('api:hybrid:dataset:version', now()->addSeconds(30), static function (): string {
            $row = DB::table('phrases')
                ->selectRaw('COUNT(*) as cnt, COALESCE(MAX(id), 0) as max_id, COALESCE(MAX(updated_at)::text, \'\') as max_updated')
                ->first();

            return (string) (($row->cnt ?? 0).':'.($row->max_id ?? 0).':'.($row->max_updated ?? ''));
        });

        $payload = Cache::remember(
            'api:hybrid:'.md5($datasetVersion.'|'.$q.'|'.$limit.'|'.implode(',', $enabled)),
            now()->addMinutes(10),
            function () use ($enabled, $q, $limit, $datasetVersion): array {
                $queryScores = $this->calculator->calculate($q, $enabled);
                $tokens = $this->tokenize($q);

                $candidateQuery = Phrase::query()
                    ->where('approved', true)
                    ->where(function ($builder) use ($tokens, $queryScores): void {
                        foreach ($tokens as $token) {
                            $builder->orWhere('phrase', 'like', '%'.$token.'%');
                        }
                        foreach ($queryScores as $cipher => $value) {
                            $builder->orWhere($cipher, (int) $value);
                        }
                    })
                    ->limit(600)
                    ->get();

                $results = $candidateQuery->map(function (Phrase $phrase) use ($tokens, $queryScores, $enabled, $q): array {
                    $candidateTokens = $this->tokenize($phrase->phrase);
                    $lexical = $this->jaccard($tokens, $candidateTokens);

                    $sharedCipherCount = 0;
                    foreach ($enabled as $cipher) {
                        if ((int) ($phrase->{$cipher} ?? 0) === (int) ($queryScores[$cipher] ?? -1)) {
                            $sharedCipherCount++;
                        }
                    }

                    $cipherSimilarity = count($enabled) > 0 ? $sharedCipherCount / count($enabled) : 0.0;
                    $hybridScore = round((($lexical * 0.55) + ($cipherSimilarity * 0.45)) * 100, 2);

                    return [
                        'id' => $phrase->id,
                        'phrase' => $phrase->phrase,
                        'url' => url('/entry/'.$phrase->id.'?source=hybrid&search_query='.urlencode($q)),
                        'hybrid_score' => $hybridScore,
                        'lexical_similarity' => round($lexical * 100, 2),
                        'cipher_similarity' => round($cipherSimilarity * 100, 2),
                        'shared_ciphers' => $sharedCipherCount,
                        'theme' => $this->resolveTheme($phrase->phrase),
                        'view_count' => (int) $phrase->view_count,
                    ];
                })
                    ->sortByDesc('hybrid_score')
                    ->take($limit)
                    ->values()
                    ->all();

                return [
                    'query' => $q,
                    'query_scores' => $queryScores,
                    'result_count' => count($results),
                    'results' => $results,
                    'cache' => [
                        'dataset_version' => $datasetVersion ?? null,
                    ],
                ];
            }
        );

        return response()->json($payload);
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        return array_values(array_filter(
            preg_split('/[^a-z0-9]+/i', Str::lower($text)) ?: [],
            static fn (string $token): bool => mb_strlen($token) >= 2
        ));
    }

    /**
     * @param array<int, string> $a
     * @param array<int, string> $b
     */
    private function jaccard(array $a, array $b): float
    {
        $setA = array_values(array_unique($a));
        $setB = array_values(array_unique($b));
        $union = array_unique(array_merge($setA, $setB));
        if ($union === []) {
            return 0.0;
        }

        $intersect = array_intersect($setA, $setB);

        return count($intersect) / count($union);
    }

    private function resolveTheme(string $phrase): string
    {
        $text = Str::lower($phrase);
        foreach (config('gematria.themes', []) as $key => $label) {
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
}
