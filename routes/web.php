<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\NewsCrawlerController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\GematriaController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\WorkspaceController;
use App\Models\Phrase;
use Illuminate\Support\Facades\Route;

Route::get('/', [GematriaController::class, 'landing'])->name('landing');
Route::get('/calculate', [GematriaController::class, 'index'])->name('calculator');
Route::redirect('/app', '/calculate', 301)->name('calculator.legacy');
Route::get('/explorer', [GematriaController::class, 'explorer'])->name('explorer');
Route::get('/entry/{phrase}', [GematriaController::class, 'show'])->name('entry.show');
Route::get('/random', [GematriaController::class, 'random'])->name('random');
Route::get('/health', HealthController::class);
Route::get('/robots.txt', static function () {
    $baseUrl = rtrim(config('app.url'), '/');
    $content = implode("\n", [
        'User-agent: *',
        'Allow: /',
        'Disallow: /admin',
        'Disallow: /workspace',
        'Disallow: /login',
        'Disallow: /register',
        '',
        'Sitemap: '.$baseUrl.'/sitemap.xml',
    ]);

    return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
});

Route::get('/llms.txt', static function () {
    $baseUrl = rtrim(config('app.url'), '/');
    $content = implode("\n", [
        '# Gematrix LLM Guide',
        '',
        '> Gematrix is a gematria research platform focused on transparent calculations and phrase matching.',
        '',
        '## Canonical URLs',
        '- Landing: '.$baseUrl.'/',
        '- Calculator: '.$baseUrl.'/calculate',
        '- Explorer: '.$baseUrl.'/explorer',
        '- Entry detail template: '.$baseUrl.'/entry/{id}',
        '',
        '## API Endpoints',
        '- GET '.$baseUrl.'/api/calculate?q={phrase}',
        '- GET '.$baseUrl.'/api/explorer?cipher={name}&value={int}&phrase={term}',
        '- GET '.$baseUrl.'/api/suggest?q={prefix}',
        '- GET '.$baseUrl.'/api/hybrid-search?q={phrase}&limit={n}',
        '',
        '## Data Notes',
        '- Phrase values are computed server-side for multiple ciphers.',
        '- Entry pages expose full cipher totals, step breakdown, and ranked matches.',
        '- Ranking uses symbolic overlap plus popularity/rarity signals.',
        '',
        '## Crawling Preference',
        '- Prefer entry detail pages for phrase-specific knowledge.',
        '- Treat admin and account pages as non-public operational UI.',
        '',
        '## Sitemaps',
        '- XML: '.$baseUrl.'/sitemap.xml',
    ]);

    return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
});

Route::get('/sitemap.xml', static function () {
    $baseUrl = rtrim(config('app.url'), '/');
    $staticUrls = [
        ['loc' => $baseUrl.'/', 'priority' => '1.0', 'changefreq' => 'daily', 'lastmod' => now()->toAtomString()],
        ['loc' => $baseUrl.'/calculate', 'priority' => '0.9', 'changefreq' => 'daily', 'lastmod' => now()->toAtomString()],
        ['loc' => $baseUrl.'/explorer', 'priority' => '0.8', 'changefreq' => 'hourly', 'lastmod' => now()->toAtomString()],
    ];

    $entries = Phrase::query()
        ->where('approved', true)
        ->orderByDesc('updated_at')
        ->limit(5000)
        ->get(['id', 'updated_at']);

    $xml = view('seo.sitemap', [
        'staticUrls' => $staticUrls,
        'entries' => $entries,
        'baseUrl' => $baseUrl,
    ])->render();

    return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/workspace', [WorkspaceController::class, 'index']);
    Route::post('/workspace/favorite', [WorkspaceController::class, 'favorite']);
    Route::delete('/workspace/favorite', [WorkspaceController::class, 'unfavorite']);
    Route::post('/workspace/phrase', [WorkspaceController::class, 'storePhrase']);
    Route::delete('/workspace/phrase/{userPhrase}', [WorkspaceController::class, 'removePhrase']);
    Route::post('/workspace/preferences', [WorkspaceController::class, 'preferences']);
    Route::post('/workspace/narrative', [WorkspaceController::class, 'storeNarrative']);
    Route::get('/workspace/narrative/{researchNarrative}', [WorkspaceController::class, 'showNarrative']);
    Route::get('/workspace/narrative/{researchNarrative}/export/markdown', [WorkspaceController::class, 'exportNarrativeMarkdown']);
    Route::get('/workspace/narrative/{researchNarrative}/export/print', [WorkspaceController::class, 'exportNarrativePrint']);
    Route::delete('/workspace/narrative/{researchNarrative}', [WorkspaceController::class, 'removeNarrative']);
});

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function (): void {
    Route::get('/', [AdminController::class, 'index']);
    Route::get('/analytics', [AdminController::class, 'analytics']);
    Route::get('/news-crawler', [NewsCrawlerController::class, 'index']);
    Route::post('/news-crawler/run', [NewsCrawlerController::class, 'run']);
    Route::post('/news-crawler/sources', [NewsCrawlerController::class, 'saveSources']);
    Route::get('/news-crawler/status', [NewsCrawlerController::class, 'status']);
    Route::post('/ciphers', [AdminController::class, 'setCiphers']);
    Route::post('/import', [AdminController::class, 'importCsv']);
    Route::post('/approve/{phrase}', [AdminController::class, 'approve']);
});
