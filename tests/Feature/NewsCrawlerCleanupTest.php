<?php

namespace Tests\Feature;

use App\Models\NewsHeadline;
use App\Models\NewsSource;
use App\Models\Phrase;
use App\Services\NewsCrawlerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsCrawlerCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_removes_trailing_source_name_and_recalculates_scores(): void
    {
        $source = NewsSource::query()->create([
            'name' => 'Times of Israel',
            'base_url' => 'https://www.timesofisrael.com',
            'rss_url' => 'https://news.google.com/rss/search?q=site:timesofisrael.com+when:1d&hl=en-US&gl=US&ceid=US:en',
            'locale' => 'en',
            'enabled' => true,
            'weight' => 100,
        ]);

        $item = NewsHeadline::query()->create([
            'news_source_id' => $source->id,
            'headline' => 'high court begins hearing on egalitarian prayer at western wall - the times of israel',
            'headline_normalized' => 'high court begins hearing on egalitarian prayer at western wall - the times of israel',
            'url' => 'https://www.timesofisrael.com/high-court-begins-hearing/',
            'url_hash' => hash('sha256', 'seed'),
            'published_at' => now(),
            'headline_date' => now()->toDateString(),
            'locale' => 'en',
            'english_gematria' => 0,
            'scores' => [],
        ]);

        Phrase::query()->create([
            'phrase' => 'high court begins hearing on egalitarian prayer at western wall - the times of israel',
            'approved' => true,
            'english_gematria' => 0,
            'simple_gematria' => 0,
            'unknown_gematria' => 0,
            'pythagoras_gematria' => 0,
            'jewish_gematria' => 0,
            'prime_gematria' => 0,
            'reverse_satanic_gematria' => 0,
            'clock_gematria' => 0,
            'reverse_clock_gematria' => 0,
            'system9_gematria' => 0,
            'english_ordinal' => 0,
            'reverse_ordinal' => 0,
            'full_reduction' => 0,
            'reverse_reduction' => 0,
            'satanic' => 0,
            'jewish' => 0,
            'chaldean' => 0,
            'primes' => 0,
            'trigonal' => 0,
            'squares' => 0,
        ]);

        $result = app(NewsCrawlerService::class)->cleanupStoredHeadlinesWithSourceSuffix(100);

        $item->refresh();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['deduped']);
        $this->assertSame(
            'high court begins hearing on egalitarian prayer at western wall',
            $item->headline
        );
        $this->assertGreaterThan(0, $item->english_gematria);
        $this->assertDatabaseMissing('phrases', [
            'phrase' => 'high court begins hearing on egalitarian prayer at western wall - the times of israel',
        ]);
        $this->assertDatabaseHas('phrases', [
            'phrase' => 'high court begins hearing on egalitarian prayer at western wall',
        ]);
    }
}
