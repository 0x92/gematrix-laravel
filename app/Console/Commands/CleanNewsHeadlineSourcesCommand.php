<?php

namespace App\Console\Commands;

use App\Services\NewsCrawlerService;
use Illuminate\Console\Command;

class CleanNewsHeadlineSourcesCommand extends Command
{
    protected $signature = 'news:clean-source-suffixes
                            {--limit=5000 : Max headlines to clean in this run}
                            {--all : Process all stored headlines}';

    protected $description = 'Remove trailing source URL/name suffixes from stored headlines and refresh gematria values';

    public function handle(NewsCrawlerService $service): int
    {
        $runAll = (bool) $this->option('all');
        $limit = $runAll ? null : max(1, (int) $this->option('limit'));
        $result = $service->cleanupStoredHeadlinesWithSourceSuffix($limit);

        $this->info(
            'Cleaned source suffixes: processed='.(int) ($result['processed'] ?? 0).
            ' updated='.(int) ($result['updated'] ?? 0).
            ' deduped='.(int) ($result['deduped'] ?? 0).
            ' phrases_synced='.(int) ($result['phrases_synced'] ?? 0).
            ' words_synced='.(int) ($result['words_synced'] ?? 0)
        );

        return self::SUCCESS;
    }
}
