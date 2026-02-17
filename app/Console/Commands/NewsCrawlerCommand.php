<?php

namespace App\Console\Commands;

use App\Services\NewsCrawlerService;
use Illuminate\Console\Command;

class NewsCrawlerCommand extends Command
{
    protected $signature = 'news:crawl-headlines
                            {--source-id=* : Optional list of source IDs to crawl}
                            {--limit=80 : Max headlines per source}';

    protected $description = 'Crawl international news sources and ingest daily headlines with gematria scores';

    public function handle(NewsCrawlerService $service): int
    {
        $sourceIds = array_values(array_filter(array_map(static fn ($v): int => (int) $v, (array) $this->option('source-id'))));
        $limit = max(10, min(500, (int) $this->option('limit')));

        $run = $service->run($sourceIds !== [] ? $sourceIds : null, $limit);

        $this->info('Crawler run #'.$run->id.' status='.$run->status.' processed='.$run->processed_count.' inserted='.$run->inserted_count);
        if ($run->error_message) {
            $this->warn($run->error_message);
        }

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
