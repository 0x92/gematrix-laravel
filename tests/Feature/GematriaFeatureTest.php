<?php

namespace Tests\Feature;

use App\Models\Phrase;
use App\Models\User;
use App\Services\GematriaCalculator;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GematriaFeatureTest extends TestCase
{
    private function createPhrase(): void
    {
        $scores = app(GematriaCalculator::class)->calculateAll('matrix code');

        Phrase::query()->updateOrCreate([
            'phrase' => 'matrix code',
        ], [
            ...$scores,
            'approved' => true,
        ]);
    }

    public function test_home_and_health_routes_work(): void
    {
        $this->createPhrase();

        $this->get('/')->assertOk()->assertSee('name="description"', false)->assertSee('application/ld+json', false);
        $this->get('/health')->assertOk()->assertJsonStructure(['status', 'db', 'cache']);
    }

    public function test_seo_routes_are_available(): void
    {
        $this->createPhrase();

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap:', false);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Gematrix LLM Guide', false)
            ->assertSee('/api/calculate', false);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset', false)
            ->assertSee('/entry/', false);
    }

    public function test_api_calculate_and_suggest_work(): void
    {
        $this->createPhrase();

        $this->getJson('/api/calculate?q=matrix')
            ->assertOk()
            ->assertJsonStructure(['query', 'scores']);

        $suggestions = $this->getJson('/api/suggest?q=ma')
            ->assertOk()
            ->json();

        $this->assertIsArray($suggestions);
        $this->assertGreaterThanOrEqual(1, count($suggestions));

        $this->getJson('/api/hybrid-search?q=matrix')
            ->assertOk()
            ->assertJsonStructure(['query', 'query_scores', 'result_count', 'results']);
    }

    public function test_auth_and_workspace_flow(): void
    {
        $this->withoutMiddleware();

        $email = 'user+'.uniqid('', true).'@example.com';

        $this->post('/register', [
            'name' => 'User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/workspace');

        $user = User::query()->where('email', $email)->firstOrFail();

        $this->actingAs($user)
            ->post('/workspace/favorite', ['phrase' => 'matrix code'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post('/workspace/preferences', [
                'theme' => 'light',
                'locale_preference' => 'en',
                'preferred_ciphers' => ['english_gematria', 'simple_gematria', 'prime_gematria'],
            ])->assertRedirect();

        $this->actingAs($user)->get('/workspace')->assertOk();

        if (Schema::hasTable('research_narratives')) {
            $this->actingAs($user)->post('/workspace/narrative', [
                'title' => 'Test narrative',
                'summary' => 'A short research story',
                'steps_raw' => "Step 1\nStep 2",
                'phrases_raw' => "matrix code\ncomputer",
            ])->assertRedirect();

            $narrativeId = $user->narratives()->latest('id')->value('id');
            $this->assertNotNull($narrativeId);
        }
    }
}
