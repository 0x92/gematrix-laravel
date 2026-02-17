<?php

namespace App\Services;

use App\Models\CrawlerRun;
use App\Models\NewsHeadline;
use App\Models\NewsSource;
use App\Models\Phrase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

class NewsCrawlerService
{
    public const CRAWLER_NAME = 'international_news_headlines';

    public function __construct(
        private readonly GematriaCalculator $calculator,
        private readonly NumberWordNormalizer $normalizer,
    ) {
    }

    public function ensureDefaultSources(): void
    {
        foreach (config('news_crawler.default_sources', []) as $row) {
            if (! is_array($row) || ! isset($row['name'], $row['rss_url'])) {
                continue;
            }

            NewsSource::query()->firstOrCreate(
                ['name' => (string) $row['name']],
                [
                    'base_url' => $row['base_url'] ?? null,
                    'rss_url' => $row['rss_url'] ?? null,
                    'locale' => $row['locale'] ?? 'en',
                    'enabled' => true,
                    'weight' => 100,
                ]
            );
        }
    }

    /**
     * @param array<int, int>|null $sourceIds
     */
    public function run(?array $sourceIds = null, int $limitPerSource = 80): CrawlerRun
    {
        $this->ensureDefaultSources();

        $run = CrawlerRun::query()->create([
            'crawler_name' => self::CRAWLER_NAME,
            'status' => 'running',
            'started_at' => now(),
            'processed_count' => 0,
            'inserted_count' => 0,
            'meta' => ['limit_per_source' => $limitPerSource],
        ]);

        $processed = 0;
        $inserted = 0;
        $phraseSynced = 0;
        $wordSynced = 0;

        try {
            $sourcesQuery = NewsSource::query()->where('enabled', true)->orderByDesc('weight')->orderBy('name');
            if (is_array($sourceIds) && $sourceIds !== []) {
                $sourcesQuery->whereIn('id', $sourceIds);
            }
            $sources = $sourcesQuery->get();

            foreach ($sources as $source) {
                $run->update(['current_item' => $source->name]);
                try {
                    $items = $this->fetchHeadlines((string) $source->rss_url, $limitPerSource);
                    foreach ($items as $item) {
                        $headline = $this->sanitizeHeadline(
                            (string) ($item['title'] ?? ''),
                            $source->name,
                            isset($item['url']) ? (string) $item['url'] : null
                        );
                        if ($headline === '' || $this->containsMojibakeMarkers($headline) || ! $this->isLikelyEnglishHeadline($headline)) {
                            continue;
                        }

                        $normalized = $this->normalizer->normalizeHeadline($headline);
                        if (mb_strlen($normalized) < 3 || mb_strlen($normalized) > 500) {
                            continue;
                        }

                        $publishedAt = $this->parseDate((string) ($item['published_at'] ?? ''));
                        $headlineDate = ($publishedAt ?? now())->toDateString();
                        $url = isset($item['url']) ? trim((string) $item['url']) : null;
                        $headlineKey = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $headline) ?? $headline));
                        $urlHash = $this->buildUrlHash((int) $source->id, $url, $headlineKey, $headlineDate);
                        $scores = $this->calculator->calculateAll($normalized);

                        $exists = NewsHeadline::query()->where('url_hash', $urlHash)->exists();
                        NewsHeadline::query()->updateOrCreate(
                            ['url_hash' => $urlHash],
                            [
                                'news_source_id' => $source->id,
                                'headline' => $headline,
                                'headline_normalized' => $normalized,
                                'url' => $url,
                                'published_at' => $publishedAt,
                                'headline_date' => $headlineDate,
                                'locale' => $source->locale,
                                'english_gematria' => (int) ($scores['english_gematria'] ?? 0),
                                'scores' => $scores,
                            ]
                        );

                        if ($this->syncPhrase($normalized, $scores)) {
                            $phraseSynced++;
                        }

                        foreach ($this->extractHeadlineWords($normalized) as $word) {
                            $wordScores = $this->calculator->calculateAll($word);
                            if ($this->syncPhrase($word, $wordScores)) {
                                $wordSynced++;
                            }
                        }

                        $processed++;
                        if (! $exists) {
                            $inserted++;
                        }
                    }

                    $source->update([
                        'last_checked_at' => now(),
                        'last_error' => null,
                    ]);
                } catch (\Throwable $e) {
                    $source->update([
                        'last_checked_at' => now(),
                        'last_error' => mb_substr($e->getMessage(), 0, 1000),
                    ]);
                }

                $run->update([
                    'processed_count' => $processed,
                    'inserted_count' => $inserted,
                    'meta' => [
                        'limit_per_source' => $limitPerSource,
                        'phrases_synced' => $phraseSynced,
                        'single_words_synced' => $wordSynced,
                    ],
                ]);
            }

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'current_item' => null,
                'processed_count' => $processed,
                'inserted_count' => $inserted,
                'meta' => [
                    'limit_per_source' => $limitPerSource,
                    'phrases_synced' => $phraseSynced,
                    'single_words_synced' => $wordSynced,
                ],
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
                'processed_count' => $processed,
                'inserted_count' => $inserted,
                'meta' => [
                    'limit_per_source' => $limitPerSource,
                    'phrases_synced' => $phraseSynced,
                    'single_words_synced' => $wordSynced,
                ],
            ]);
        }

        return $run->fresh();
    }

    /**
     * @return array<int, array{title: string, url: string|null, published_at: string|null}>
     */
    private function fetchHeadlines(string $rssUrl, int $limit): array
    {
        if ($rssUrl === '') {
            return [];
        }

        $response = Http::withHeaders([
            'User-Agent' => 'GematrixNewsCrawler/1.0',
            'Accept' => 'application/rss+xml,application/atom+xml,application/xml,text/xml,*/*',
        ])->timeout(20)->retry(2, 250)->get($rssUrl);

        if (! $response->ok()) {
            throw new \RuntimeException('RSS request failed: '.$response->status());
        }

        $xml = @simplexml_load_string((string) $response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
        if (! $xml) {
            throw new \RuntimeException('Invalid XML feed');
        }

        $rows = [];

        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $rows[] = [
                    'title' => trim((string) ($item->title ?? '')),
                    'url' => trim((string) ($item->link ?? '')) ?: null,
                    'published_at' => trim((string) ($item->pubDate ?? '')) ?: null,
                ];
                if (count($rows) >= $limit) {
                    break;
                }
            }
        } elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $link = null;
                if (isset($entry->link)) {
                    foreach ($entry->link as $lnk) {
                        $attrs = $lnk->attributes();
                        $href = isset($attrs['href']) ? trim((string) $attrs['href']) : trim((string) $lnk);
                        if ($href !== '') {
                            $link = $href;
                            break;
                        }
                    }
                }

                $rows[] = [
                    'title' => trim((string) ($entry->title ?? '')),
                    'url' => $link,
                    'published_at' => trim((string) ($entry->updated ?? $entry->published ?? '')) ?: null,
                ];
                if (count($rows) >= $limit) {
                    break;
                }
            }
        }

        return array_values(array_filter($rows, static fn (array $row): bool => ($row['title'] ?? '') !== ''));
    }

    private function parseDate(string $raw): ?CarbonImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{processed: int, updated: int, deduped: int, phrases_synced: int, words_synced: int}
     */
    public function cleanupStoredHeadlinesWithSourceSuffix(?int $limit = 5000): array
    {
        $updated = 0;
        $deduped = 0;
        $phrasesSynced = 0;
        $wordsSynced = 0;
        $processed = 0;
        $max = $limit !== null ? max(1, (int) $limit) : null;

        NewsHeadline::query()
            ->with('source:id,name')
            ->orderBy('id')
            ->chunkById(500, function ($items) use (&$updated, &$deduped, &$phrasesSynced, &$wordsSynced, &$processed, $max): bool {
                foreach ($items as $item) {
                    if ($max !== null && $processed >= $max) {
                        return false;
                    }
                    $processed++;

                    $clean = $this->sanitizeHeadline($item->headline, $item->source?->name, $item->url);
                    if ($clean === '' || $this->containsMojibakeMarkers($clean)) {
                        $item->delete();
                        $deduped++;
                        continue;
                    }
                    if (! $this->isLikelyEnglishHeadline($clean)) {
                        $item->delete();
                        $deduped++;
                        continue;
                    }
                    if ($clean === $item->headline) {
                        continue;
                    }

                    $oldHeadline = (string) $item->headline;
                    $oldNormalized = trim(mb_strtolower((string) $item->headline_normalized));
                    if ($oldNormalized === '') {
                        $oldNormalized = $this->normalizer->normalizeHeadline($oldHeadline);
                    }

                    $normalized = $this->normalizer->normalizeHeadline($clean);
                    if ($normalized === '') {
                        continue;
                    }
                    $scores = $this->calculator->calculateAll($normalized);
                    $headlineDate = ($item->headline_date ?? now())->toDateString();
                    $url = $item->url ? trim((string) $item->url) : null;
                    $headlineKey = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean));
                    $sourceId = (int) ($item->news_source_id ?? 0);
                    $newHash = $this->buildUrlHash($sourceId, $url, $headlineKey, $headlineDate);

                    $conflict = NewsHeadline::query()
                        ->where('url_hash', $newHash)
                        ->where('id', '!=', $item->id)
                        ->exists();

                    if ($conflict) {
                        $item->delete();
                        $deduped++;
                        continue;
                    }

                    $item->update([
                        'headline' => $clean,
                        'headline_normalized' => $normalized,
                        'url_hash' => $newHash,
                        'english_gematria' => (int) ($scores['english_gematria'] ?? 0),
                        'scores' => $scores,
                    ]);
                    $updated++;

                    if ($this->syncPhrase($normalized, $scores)) {
                        $phrasesSynced++;
                    }
                    foreach ($this->extractHeadlineWords($normalized) as $word) {
                        $wordScores = $this->calculator->calculateAll($word);
                        if ($this->syncPhrase($word, $wordScores)) {
                            $wordsSynced++;
                        }
                    }

                    if (
                        $oldNormalized !== ''
                        && $oldNormalized !== $normalized
                        && $this->headlineHasSourceSuffix($oldHeadline, $item->source?->name, $item->url)
                    ) {
                        $this->deleteOrphanedPhrase($oldNormalized);
                    }
                }

                return true;
            });

        return [
            'processed' => $processed,
            'updated' => $updated,
            'deduped' => $deduped,
            'phrases_synced' => $phrasesSynced,
            'words_synced' => $wordsSynced,
        ];
    }

    private function sanitizeHeadline(string $headline, ?string $sourceName = null, ?string $url = null): string
    {
        $headline = html_entity_decode($headline, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Repair common mojibake sequences seen in news feeds (UTF-8 bytes decoded as Latin-1/CP1252).
        $headline = strtr($headline, [
            'â€™' => "'",
            'â€˜' => "'",
            'â€œ' => '"',
            'â€' => '"',
            'â€"' => '-',
            'â€“' => '-',
            'â€”' => '-',
            'â€¦' => '...',
            'â„¢' => '(TM)',
            'â‚¬' => 'EUR',
            'â‚¬' => 'EUR',
            'Ã¡' => 'á',
            'Ã©' => 'é',
            'Ã¨' => 'è',
            'Ã­' => 'í',
            'Ã³' => 'ó',
            'Ã¶' => 'ö',
            'Ãº' => 'ú',
            'Ã¼' => 'ü',
            'Ã±' => 'ñ',
            'ã¡' => 'á',
            'ã©' => 'é',
            'ã¨' => 'è',
            'ã­' => 'í',
            'ã³' => 'ó',
            'ã¶' => 'ö',
            'ãº' => 'ú',
            'ã¼' => 'ü',
            'ã±' => 'ñ',
            'Â' => '',
            'âeur' => '"',
            'â,¬' => 'EUR',
        ]);

        $headline = preg_replace('/âEURs/iu', "'s", $headline) ?? $headline;
        $headline = preg_replace('/âEURt/iu', "'t", $headline) ?? $headline;
        $headline = preg_replace('/âEURre/iu', "'re", $headline) ?? $headline;
        $headline = preg_replace('/âEURve/iu', "'ve", $headline) ?? $headline;
        $headline = preg_replace('/âEURm/iu', "'m", $headline) ?? $headline;
        $headline = preg_replace('/âEURll/iu', "'ll", $headline) ?? $headline;
        $headline = preg_replace('/âEURd/iu', "'d", $headline) ?? $headline;
        $headline = preg_replace('/âEUR-/', '-', $headline) ?? $headline;
        $headline = preg_replace('/âEURoe/iu', '"', $headline) ?? $headline;
        $headline = preg_replace('/âEUR/iu', '"', $headline) ?? $headline;

        $headline = strtr($headline, [
            "\x80" => 'EUR',
            "\x82" => ',',
            "\x83" => 'f',
            "\x84" => ',,',
            "\x85" => '...',
            "\x86" => '+',
            "\x87" => '+',
            "\x88" => '^',
            "\x89" => '%',
            "\x8A" => 'S',
            "\x8B" => '<',
            "\x8C" => 'OE',
            "\x8E" => 'Z',
            "\x91" => "'",
            "\x92" => "'",
            "\x93" => '"',
            "\x94" => '"',
            "\x95" => '*',
            "\x96" => '-',
            "\x97" => '-',
            "\x98" => '',
            "\x99" => '(TM)',
            "\x9A" => 's',
            "\x9B" => '>',
            "\x9C" => 'oe',
            "\x9E" => 'z',
            "\x9F" => 'Y',
        ]);

        $headline = mb_convert_encoding($headline, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
        $headline = preg_replace('/\(\s*tm\s*\)/iu', '', $headline) ?? $headline;
        // Drop stray control characters while preserving common whitespace.
        $headline = preg_replace('/[^\P{C}\t\n\r]/u', ' ', $headline) ?? $headline;
        if (substr_count($headline, '"') % 2 === 1) {
            $headline = preg_replace('/"\s+([a-z][^"]*)$/iu', '- $1', $headline) ?? $headline;
        }
        $headline = trim(preg_replace('/\s+/u', ' ', $headline) ?? '');
        if ($headline === '') {
            return '';
        }

        // Remove trailing source fragments like " - bloomberg.com", " | Reuters", " — BBC News".
        $headline = preg_replace('/\s+[-|—:]\s+((?:www\.)?(?:[a-z0-9-]+\.)+[a-z]{2,})$/iu', '', $headline) ?? $headline;

        foreach ($this->buildSourceSuffixCandidates($sourceName, $url) as $candidate) {
            $candidateEscaped = preg_quote($candidate, '/');
            $headline = preg_replace('/\s+[-|—:]\s+'.$candidateEscaped.'$/iu', '', $headline) ?? $headline;
        }

        return trim($headline, " \t\n\r\0\x0B-—|:");
    }

    /**
     * @return array<int, string>
     */
    private function buildSourceSuffixCandidates(?string $sourceName, ?string $url): array
    {
        $candidates = [];
        $addCandidate = static function (?string $value) use (&$candidates): void {
            if (! is_string($value)) {
                return;
            }
            $normalized = trim(preg_replace('/\s+/u', ' ', mb_strtolower($value)) ?? '');
            if ($normalized === '' || mb_strlen($normalized) < 2) {
                return;
            }
            $candidates[$normalized] = true;
        };

        if (is_string($sourceName) && trim($sourceName) !== '') {
            $normalizedSource = $this->normalizeSourceToken($sourceName);
            $addCandidate($normalizedSource);
            $addCandidate(preg_replace('/\.[a-z]{2,}$/iu', '', $normalizedSource) ?? $normalizedSource);
        }

        if (is_string($url) && trim($url) !== '') {
            $host = parse_url($url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $host = mb_strtolower(trim($host));
                $parts = array_values(array_filter(
                    explode('.', $host),
                    static fn (string $part): bool => $part !== '' && ! in_array($part, ['www', 'm', 'mobile', 'news', 'feeds'], true)
                ));

                if ($parts !== []) {
                    $withoutTld = $parts;
                    array_pop($withoutTld);
                    if ($withoutTld !== []) {
                        $addCandidate($this->normalizeSourceToken(end($withoutTld) ?: ''));
                        $addCandidate($this->normalizeSourceToken(implode(' ', $withoutTld)));
                    }
                }
            }
        }

        foreach (array_keys($candidates) as $candidate) {
            if (str_starts_with($candidate, 'the ')) {
                $addCandidate(substr($candidate, 4));
            } else {
                $addCandidate('the '.$candidate);
            }
        }

        return array_keys($candidates);
    }

    private function normalizeSourceToken(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}\s&]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function headlineHasSourceSuffix(string $headline, ?string $sourceName, ?string $url): bool
    {
        foreach ($this->buildSourceSuffixCandidates($sourceName, $url) as $candidate) {
            $candidateEscaped = preg_quote($candidate, '/');
            if (preg_match('/\s+[-|—:]\s+'.$candidateEscaped.'$/iu', $headline)) {
                return true;
            }
        }

        return false;
    }

    private function deleteOrphanedPhrase(string $phrase): void
    {
        $phrase = trim(mb_strtolower($phrase));
        if ($phrase === '') {
            return;
        }

        $stillUsed = NewsHeadline::query()
            ->where('headline_normalized', $phrase)
            ->exists();

        if (! $stillUsed) {
            Phrase::query()->where('phrase', $phrase)->delete();
        }
    }

    private function buildUrlHash(int $sourceId, ?string $url, string $headlineKey, string $headlineDate): string
    {
        $dedupeBase = $url ?: ($headlineKey.'|'.$headlineDate);
        return hash('sha256', $sourceId.'|'.$dedupeBase);
    }

    private function containsMojibakeMarkers(string $headline): bool
    {
        return (bool) preg_match('/[âÃãÂ]/u', $headline);
    }

    private function isLikelyEnglishHeadline(string $headline): bool
    {
        $headline = trim($headline);
        if ($headline === '') {
            return false;
        }

        preg_match_all('/[A-Za-z]/', $headline, $latinMatches);
        preg_match_all('/\p{L}/u', $headline, $allLetterMatches);
        $latin = count($latinMatches[0] ?? []);
        $allLetters = count($allLetterMatches[0] ?? []);

        if ($latin < 6) {
            return false;
        }
        if ($allLetters === 0) {
            return false;
        }

        return ($latin / $allLetters) >= 0.7;
    }

    /**
     * @param array<string, int> $scores
     */
    private function syncPhrase(string $phrase, array $scores): bool
    {
        $phrase = trim(mb_strtolower($phrase));
        if (mb_strlen($phrase) < 2 || mb_strlen($phrase) > 500) {
            return false;
        }

        $existing = Phrase::query()->where('phrase', $phrase)->first();
        if ($existing) {
            $existing->fill($this->phraseAttributes($scores))->save();
            return false;
        }

        Phrase::query()->create(array_merge(
            ['phrase' => $phrase, 'approved' => true],
            $this->phraseAttributes($scores)
        ));

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function extractHeadlineWords(string $headline): array
    {
        preg_match_all('/[a-z]+(?:-[a-z]+)*/', mb_strtolower($headline), $matches);
        $tokens = $matches[0] ?? [];
        $tokens = array_map(static fn (string $w): string => trim($w), $tokens);
        $tokens = array_filter($tokens, static function (string $word): bool {
            $length = mb_strlen($word);
            return $length >= 3 && $length <= 40;
        });

        return array_values(array_unique($tokens));
    }

    /**
     * @param array<string, int> $scores
     * @return array<string, int>
     */
    private function phraseAttributes(array $scores): array
    {
        return [
            'english_gematria' => (int) ($scores['english_gematria'] ?? 0),
            'simple_gematria' => (int) ($scores['simple_gematria'] ?? 0),
            'unknown_gematria' => (int) ($scores['unknown_gematria'] ?? 0),
            'pythagoras_gematria' => (int) ($scores['pythagoras_gematria'] ?? 0),
            'jewish_gematria' => (int) ($scores['jewish_gematria'] ?? 0),
            'prime_gematria' => (int) ($scores['prime_gematria'] ?? 0),
            'reverse_satanic_gematria' => (int) ($scores['reverse_satanic_gematria'] ?? 0),
            'clock_gematria' => (int) ($scores['clock_gematria'] ?? 0),
            'reverse_clock_gematria' => (int) ($scores['reverse_clock_gematria'] ?? 0),
            'system9_gematria' => (int) ($scores['system9_gematria'] ?? 0),
            'francis_bacon_gematria' => (int) ($scores['francis_bacon_gematria'] ?? 0),
            'septenary_gematria' => (int) ($scores['septenary_gematria'] ?? 0),
            'glyph_geometry_gematria' => (int) ($scores['glyph_geometry_gematria'] ?? 0),
            'english_ordinal' => (int) ($scores['english_ordinal'] ?? 0),
            'reverse_ordinal' => (int) ($scores['reverse_ordinal'] ?? 0),
            'full_reduction' => (int) ($scores['full_reduction'] ?? 0),
            'reverse_reduction' => (int) ($scores['reverse_reduction'] ?? 0),
            'satanic' => (int) ($scores['satanic'] ?? 0),
            'jewish' => (int) ($scores['jewish'] ?? 0),
            'chaldean' => (int) ($scores['chaldean'] ?? 0),
            'primes' => (int) ($scores['primes'] ?? 0),
            'trigonal' => (int) ($scores['trigonal'] ?? 0),
            'squares' => (int) ($scores['squares'] ?? 0),
        ];
    }
}
