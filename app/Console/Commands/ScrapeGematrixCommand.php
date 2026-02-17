<?php

namespace App\Console\Commands;

use App\Services\GematriaCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ScrapeGematrixCommand extends Command
{
    protected $signature = 'gematrix:scrape-import
                            {--base=https://gematrix.org : Base URL to crawl}
                            {--start=/ : Comma-separated start paths}
                            {--use-sitemap=1 : Discover and enqueue URLs from sitemap.xml}
                            {--max-pages=4000 : Maximum pages to crawl}
                            {--delay-ms=250 : Delay between requests in milliseconds}
                            {--timeout=20 : Request timeout in seconds}
                            {--batch=300 : Upsert batch size}
                            {--min-len=2 : Minimum phrase length}
                            {--max-len=120 : Maximum phrase length}
                            {--state=storage/app/gematrix_scrape_state.json : Resume state file path}
                            {--resume : Resume from previous state file}
                            {--dry-run : Crawl and parse without writing to DB}';

    protected $description = 'Crawl gematrix.org-like websites and import discovered words/phrases into phrases table';

    private const DEFAULT_USER_AGENT = 'GematrixOpsImporter/1.0 (+https://gematrix.org)';

    /** @var array<string, bool> */
    private array $seen = [];

    /** @var array<int, string> */
    private array $queue = [];

    /** @var array<string, bool> */
    private array $phraseSeen = [];

    /** @var array<int, array<string, mixed>> */
    private array $batch = [];

    /** @var array<int, string> */
    private array $ignoreTexts = [
        'landing', 'calculator', 'explorer', 'workspace', 'login', 'register', 'logout', 'admin',
        'analytics', 'apply', 'reset', 'next', 'prev', 'open', 'detail', 'details', 'random',
        'copy link', 'how it works', 'api', 'sitemap', 'robots', 'llms', 'gematrix', 'gematrix ops',
    ];

    public function handle(GematriaCalculator $calculator): int
    {
        DB::connection()->disableQueryLog();

        $base = rtrim((string) $this->option('base'), '/');
        $starts = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('start')))));
        $useSitemap = (string) $this->option('use-sitemap') !== '0';
        $maxPages = max(1, (int) $this->option('max-pages'));
        $delayMs = max(0, (int) $this->option('delay-ms'));
        $timeout = max(3, (int) $this->option('timeout'));
        $batchSize = max(50, min(1000, (int) $this->option('batch')));
        $minLen = max(1, (int) $this->option('min-len'));
        $maxLen = max($minLen, (int) $this->option('max-len'));
        $dryRun = (bool) $this->option('dry-run');
        $resume = (bool) $this->option('resume');
        $statePath = base_path((string) $this->option('state'));

        if ($starts === []) {
            $starts = ['/'];
        }

        if ($resume && is_file($statePath)) {
            $loaded = $this->loadState($statePath);
            $this->seen = $loaded['seen'];
            $this->queue = $loaded['queue'];
            $this->phraseSeen = $loaded['phrase_seen'];
            $this->info('Resumed state from '.$statePath.' (queue='.count($this->queue).', seen='.count($this->seen).').');
        } else {
            foreach ($starts as $path) {
                $url = $this->canonicalizeUrl($base, $path);
                if ($url !== null) {
                    $this->queue[] = $url;
                }
            }
            if ($useSitemap) {
                $this->line('Discovering sitemap URLs...');
                foreach ($this->discoverSitemapUrls($base) as $sitemapUrl) {
                    if (! isset($this->seen[$sitemapUrl])) {
                        $this->queue[] = $sitemapUrl;
                    }
                }
            }
            $this->queue = array_values(array_unique($this->queue));
        }

        $this->warn('Ensure you have permission to crawl/import this content and comply with robots.txt/terms.');

        $processed = 0;
        $parsedCandidates = 0;
        $importedPhrases = 0;

        while ($this->queue !== [] && $processed < $maxPages) {
            $url = array_shift($this->queue);
            if (! is_string($url) || isset($this->seen[$url])) {
                continue;
            }
            $this->seen[$url] = true;

            $processed++;
            $this->line("[{$processed}/{$maxPages}] {$url}");

            $response = Http::withHeaders([
                'User-Agent' => self::DEFAULT_USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
            ])->timeout($timeout)->retry(2, 300)->get($url);

            if (! $response->ok()) {
                $this->warn('  -> skipped (HTTP '.$response->status().')');
                $this->persistState($statePath);
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }

                continue;
            }

            $contentType = (string) $response->header('Content-Type');
            $body = (string) $response->body();

            $links = [];
            $phrases = [];

            if (str_contains(strtolower($contentType), 'json')) {
                $phrases = $this->extractPhrasesFromJson($body);
            } else {
                [$links, $phrases] = $this->extractFromHtml($base, $url, $body);
            }

            $addedFromPage = 0;
            foreach ($phrases as $phrase) {
                $normalized = $this->normalizePhrase($phrase, $minLen, $maxLen);
                if ($normalized === null || isset($this->phraseSeen[$normalized])) {
                    continue;
                }

                $this->phraseSeen[$normalized] = true;
                $parsedCandidates++;
                $addedFromPage++;

                if (! $dryRun) {
                    $scores = $calculator->calculateAll($normalized);
                    $this->batch[] = array_merge(
                        ['phrase' => $normalized],
                        $scores,
                        ['approved' => true, 'created_at' => now(), 'updated_at' => now()]
                    );

                    if (count($this->batch) >= $batchSize) {
                        $importedPhrases += $this->flushBatch();
                    }
                }
            }

            foreach ($links as $link) {
                if (! isset($this->seen[$link])) {
                    $this->queue[] = $link;
                }
            }

            $this->queue = array_values(array_unique($this->queue));

            $this->line('  -> candidates: '.$addedFromPage.', queue: '.count($this->queue));
            $this->persistState($statePath);

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        if (! $dryRun && $this->batch !== []) {
            $importedPhrases += $this->flushBatch();
        }

        $this->persistState($statePath);

        $this->info('Done.');
        $this->info('Pages crawled: '.$processed);
        $this->info('Unique phrase candidates: '.$parsedCandidates);
        $this->info('Imported/updated phrases: '.($dryRun ? 0 : $importedPhrases));
        $this->info('State file: '.$statePath);

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function extractFromHtml(string $base, string $url, string $html): array
    {
        $links = [];
        $phrases = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (! @$dom->loadHTML($html)) {
            libxml_clear_errors();

            return [[], []];
        }
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }
            $href = $node->getAttribute('href');
            $absolute = $this->canonicalizeUrl($base, $href, $url);
            if ($absolute !== null) {
                $links[] = $absolute;
            }

            $text = trim($node->textContent ?? '');
            if ($text !== '') {
                $phrases[] = $text;
            }
        }

        foreach (([
            '//td[1]',
            '//tr/td',
            '//*[contains(@class, "phrase")]',
            '//li',
            '//h1|//h2|//h3',
        ]) as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $text = trim((string) $node->textContent);
                if ($text !== '') {
                    $phrases[] = $text;
                }
            }
        }

        return [array_values(array_unique($links)), array_values(array_unique($phrases))];
    }

    /**
     * @return array<int, string>
     */
    private function extractPhrasesFromJson(string $json): array
    {
        $phrases = [];
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return [];
        }

        $walk = function ($value) use (&$walk, &$phrases): void {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    if (is_string($k) && in_array(strtolower($k), ['phrase', 'query', 'text', 'title', 'name'], true) && is_string($v)) {
                        $phrases[] = $v;
                    }
                    $walk($v);
                }
            }
        };

        $walk($data);

        return array_values(array_unique($phrases));
    }

    private function normalizePhrase(string $text, int $minLen, int $maxLen): ?string
    {
        $normalized = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $normalized) ?? ''));
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, $this->ignoreTexts, true)) {
            return null;
        }

        if (mb_strlen($normalized) < $minLen || mb_strlen($normalized) > $maxLen) {
            return null;
        }

        if (! preg_match('/[a-z]/', $normalized)) {
            return null;
        }

        if (! preg_match('/^[a-z0-9\'\-\.,:\/\(\)\s]+$/', $normalized)) {
            return null;
        }

        $words = preg_split('/\s+/u', $normalized) ?: [];
        if (count($words) > 16) {
            return null;
        }

        $digitOnly = preg_replace('/[\s\W_]/u', '', $normalized) ?? '';
        if ($digitOnly !== '' && preg_match('/^\d+$/', $digitOnly)) {
            return null;
        }

        return $normalized;
    }

    private function canonicalizeUrl(string $base, string $href, ?string $current = null): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:') || str_starts_with(strtolower($href), 'mailto:')) {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            $url = $href;
        } else {
            $baseParts = parse_url($base);
            if (! is_array($baseParts) || ! isset($baseParts['scheme'], $baseParts['host'])) {
                return null;
            }

            $currentParts = $current ? parse_url($current) : $baseParts;
            $currentPath = (string) ($currentParts['path'] ?? '/');
            $dir = str_ends_with($currentPath, '/') ? $currentPath : (dirname($currentPath).'/');
            $rawPath = str_starts_with($href, '/') ? $href : ($dir.$href);
            $normalizedPath = $this->normalizePath($rawPath);
            if ($normalizedPath === null || strlen($normalizedPath) > 240) {
                return null;
            }

            $url = $baseParts['scheme'].'://'.$baseParts['host'].(isset($baseParts['port']) ? ':'.$baseParts['port'] : '').$normalizedPath;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $baseParts = parse_url($base);
        if (! is_array($baseParts) || ($parts['host'] ?? '') !== ($baseParts['host'] ?? '')) {
            return null;
        }

        $path = $this->normalizePath((string) ($parts['path'] ?? '/'));
        if ($path === null || strlen($path) > 240) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);
        $allowedQuery = [];
        foreach (['page', 'p', 'q', 'word', 'words', 'phrase', 'value', 'cipher', 'sort', 'dir'] as $key) {
            if (isset($query[$key]) && is_scalar($query[$key])) {
                $allowedQuery[$key] = (string) $query[$key];
            }
        }
        $queryString = $allowedQuery !== [] ? ('?'.http_build_query($allowedQuery)) : '';

        return ($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').$path.$queryString);
    }

    private function normalizePath(string $path): ?string
    {
        $path = preg_replace('#/+#', '/', $path) ?: '/';
        $segments = explode('/', $path);
        $out = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($out);
                continue;
            }
            $out[] = $segment;
        }

        $normalized = '/'.implode('/', $out);

        return $normalized === '' ? '/' : $normalized;
    }

    private function persistState(string $statePath): void
    {
        $dir = dirname($statePath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $state = [
            'seen' => array_keys($this->seen),
            'queue' => array_values($this->queue),
            'phrase_seen' => array_keys($this->phraseSeen),
            'updated_at' => now()->toIso8601String(),
        ];

        file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{seen: array<string, bool>, queue: array<int, string>, phrase_seen: array<string, bool>}
     */
    private function loadState(string $statePath): array
    {
        $raw = @file_get_contents($statePath);
        if (! is_string($raw) || $raw === '') {
            return ['seen' => [], 'queue' => [], 'phrase_seen' => []];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return ['seen' => [], 'queue' => [], 'phrase_seen' => []];
        }

        $seen = [];
        foreach (($decoded['seen'] ?? []) as $url) {
            if (is_string($url)) {
                $seen[$url] = true;
            }
        }

        $queue = [];
        foreach (($decoded['queue'] ?? []) as $url) {
            if (is_string($url)) {
                $queue[] = $url;
            }
        }

        $phraseSeen = [];
        foreach (($decoded['phrase_seen'] ?? []) as $phrase) {
            if (is_string($phrase)) {
                $phraseSeen[$phrase] = true;
            }
        }

        return [
            'seen' => $seen,
            'queue' => array_values(array_unique($queue)),
            'phrase_seen' => $phraseSeen,
        ];
    }

    private function flushBatch(): int
    {
        if ($this->batch === []) {
            return 0;
        }

        $count = count($this->batch);
        $first = $this->batch[0];
        $columnsToUpdate = array_values(array_filter(
            array_keys($first),
            static fn (string $key): bool => ! in_array($key, ['phrase', 'created_at'], true)
        ));

        DB::table('phrases')->upsert($this->batch, ['phrase'], $columnsToUpdate);
        $this->batch = [];

        return $count;
    }

    /**
     * @return array<int, string>
     */
    private function discoverSitemapUrls(string $base): array
    {
        $sitemapRoot = rtrim($base, '/').'/sitemap.xml';
        $collected = [];
        $queue = [$sitemapRoot];
        $seenSitemaps = [];

        while ($queue !== []) {
            $sitemapUrl = array_shift($queue);
            if (! is_string($sitemapUrl) || isset($seenSitemaps[$sitemapUrl])) {
                continue;
            }
            $seenSitemaps[$sitemapUrl] = true;

            try {
                $response = Http::withHeaders(['User-Agent' => self::DEFAULT_USER_AGENT])
                    ->timeout(20)
                    ->retry(1, 250)
                    ->get($sitemapUrl);
            } catch (\Throwable) {
                continue;
            }

            if (! $response->ok()) {
                continue;
            }

            $xml = @simplexml_load_string((string) $response->body());
            if (! $xml) {
                continue;
            }

            if (isset($xml->sitemap)) {
                foreach ($xml->sitemap as $node) {
                    $loc = trim((string) ($node->loc ?? ''));
                    $canonical = $this->canonicalizeUrl($base, $loc);
                    if ($canonical !== null) {
                        $queue[] = $canonical;
                    }
                }
            }

            if (isset($xml->url)) {
                foreach ($xml->url as $node) {
                    $loc = trim((string) ($node->loc ?? ''));
                    $canonical = $this->canonicalizeUrl($base, $loc);
                    if ($canonical !== null) {
                        $collected[] = $canonical;
                    }
                }
            }
        }

        return array_values(array_unique($collected));
    }
}
