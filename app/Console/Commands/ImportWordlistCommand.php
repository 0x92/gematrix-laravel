<?php

namespace App\Console\Commands;

use App\Services\GematriaCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportWordlistCommand extends Command
{
    protected $signature = 'words:import
                            {--source=https://raw.githubusercontent.com/dwyl/english-words/master/words_alpha.txt : File path or URL}
                            {--limit=0 : Max words to import (0 = no limit)}
                            {--min=3 : Min word length}
                            {--batch=1000 : Upsert batch size}';

    protected $description = 'Import a large wordlist into phrases with precomputed gematria values';

    public function handle(GematriaCalculator $calculator): int
    {
        ini_set('memory_limit', '1024M');
        DB::connection()->disableQueryLog();

        $source = (string) $this->option('source');
        $limit = (int) $this->option('limit');
        $min = max(1, (int) $this->option('min'));
        $batchSize = max(100, (int) $this->option('batch'));
        $maxBatchForPgParams = 2500;
        $batchSize = min($batchSize, $maxBatchForPgParams);

        $this->info("Reading source: {$source}");
        $handle = @fopen($source, 'r');
        if (! $handle) {
            $this->error('Could not open source. Use a local path or reachable URL.');

            return self::FAILURE;
        }

        $processed = 0;
        $accepted = 0;
        $batch = [];
        $started = microtime(true);

        while (($line = fgets($handle)) !== false) {
            $processed++;
            $word = trim(mb_strtolower($line));

            if ($word === '' || ! preg_match('/^[a-z]+$/', $word)) {
                continue;
            }
            if (mb_strlen($word) < $min || mb_strlen($word) > 64) {
                continue;
            }

            $scores = $calculator->calculateAll($word);
            $batch[] = array_merge(
                ['phrase' => $word],
                $scores,
                ['approved' => true, 'created_at' => now(), 'updated_at' => now()]
            );

            $accepted++;

            if (count($batch) >= $batchSize) {
                $this->flushBatch($batch);
                $batch = [];
            }

            if ($limit > 0 && $accepted >= $limit) {
                break;
            }

            if ($accepted > 0 && $accepted % 20000 === 0) {
                $elapsed = microtime(true) - $started;
                $this->line("Imported {$accepted} words in ".number_format($elapsed, 1).'s');
            }
        }

        fclose($handle);

        if ($batch !== []) {
            $this->flushBatch($batch);
        }

        $elapsed = microtime(true) - $started;
        $this->info("Done. processed={$processed}, imported={$accepted}, seconds=".number_format($elapsed, 1));

        return self::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $batch
     */
    private function flushBatch(array $batch): void
    {
        $first = $batch[0] ?? [];
        $columnsToUpdate = array_values(array_filter(array_keys($first), static fn (string $key): bool => ! in_array($key, ['phrase', 'created_at'], true)));

        DB::table('phrases')->upsert($batch, ['phrase'], $columnsToUpdate);
    }
}
