<!DOCTYPE html>
<html lang="en" data-theme="business">
<head>
    @php
        $siteName = 'Gematrix';
        $defaultDescription = 'Gematrix is a professional gematria calculator with multi-cipher analysis, phrase matching, explorer search, and detailed breakdown pages.';
        $metaData = $meta ?? [];
        $metaTitle = trim((string) ($metaData['title'] ?? ($title ?? $siteName)));
        $metaDescription = trim((string) ($metaData['description'] ?? $defaultDescription));
        $canonical = (string) ($metaData['canonical'] ?? url()->current());
        $robots = (string) ($metaData['robots'] ?? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1');
        $ogType = (string) ($metaData['og_type'] ?? 'website');
        $ogImage = (string) ($metaData['og_image'] ?? asset('images/og-cover.svg'));
        $twitterCard = (string) ($metaData['twitter_card'] ?? 'summary_large_image');
        $jsonLd = $metaData['json_ld'] ?? [];
        if (! is_array($jsonLd)) {
            $jsonLd = [$jsonLd];
        }
        $metaLocale = (string) ($metaData['locale'] ?? 'en_US');
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="{{ $robots }}">
    <meta name="author" content="Gematrix">
    <link rel="canonical" href="{{ $canonical }}">
    <link rel="alternate" hreflang="en" href="{{ $canonical }}">

    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:locale" content="{{ $metaLocale }}">

    <meta name="twitter:card" content="{{ $twitterCard }}">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <meta name="theme-color" content="#05070b">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Sora:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @foreach($jsonLd as $schema)
        @if(is_array($schema) && $schema !== [])
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
    @endforeach
</head>
@php
    $routeClass = request()->route()?->getName();
    $routeClass = is_string($routeClass) ? 'route-'.str_replace('.', '-', $routeClass) : '';
@endphp
<body class="{{ $routeClass }}">
<a href="#main-content" class="sr-only focus:not-sr-only">Skip to main content</a>
<div class="bg-grid"></div>
<header class="topbar">
    <div class="topbar-inner">
        <a href="/" class="brand"><i class="fa-solid fa-user-secret"></i> Gematrix Ops</a>
        <nav class="topbar-links">
            <a class="btn btn-ghost btn-sm" href="/">Landing</a>
            <a class="btn btn-ghost btn-sm" href="/calculate">Calculator</a>
            <a class="btn btn-ghost btn-sm" href="/explorer">{{ __('messages.explorer') }}</a>
        @auth
            <a class="btn btn-ghost btn-sm" href="/workspace">{{ __('messages.workspace') }}</a>
            @if(auth()->user()->is_admin)
                <a class="btn btn-ghost btn-sm" href="/admin">Admin</a>
                <a class="btn btn-ghost btn-sm" href="/admin/analytics">Analytics</a>
            @endif
            <form method="POST" action="/logout">@csrf<button class="btn btn-outline btn-sm">{{ __('messages.logout') }}</button></form>
        @else
            <a class="btn btn-ghost btn-sm" href="/login">{{ __('messages.login') }}</a>
            <a class="btn btn-primary btn-sm" href="/register">{{ __('messages.register') }}</a>
        @endauth
        </nav>
    </div>
</header>

<main class="page" id="main-content">
    {{ $slot }}
</main>
<footer class="ops-footer">
    <div class="ops-footer-shell">
        <div class="ops-footer-inner">
            <div class="ops-footer-brand">
                <p class="kicker"><i class="fa-solid fa-shield-halved"></i> Directorate Cipher Operations</p>
                <h4>Gematrix Ops</h4>
                <p>Mission control interface for multi-cipher analysis, phrase intelligence, and operator workflows.</p>
            </div>
            <div class="ops-footer-col">
                <h5>Navigation</h5>
                <a href="/">Landing</a>
                <a href="/calculate">Calculator</a>
                <a href="/explorer">Explorer</a>
                <a href="/random">Random Entry</a>
            </div>
            <div class="ops-footer-col">
                <h5>Data & API</h5>
                <a href="/api/calculate?q=computer">API Calculate</a>
                <a href="/api/hybrid-search?q=computer">API Hybrid Search</a>
                <a href="/sitemap.xml">Sitemap</a>
                <a href="/llms.txt">LLM Guide</a>
            </div>
            <div class="ops-footer-col">
                <h5>Operational Status</h5>
                <span class="ops-badge">Clearance: Internal Operators</span>
                <span class="ops-badge">Environment: Mission Control</span>
                <span class="ops-badge">Locale: English Only</span>
                <span class="ops-badge">Theme: Dark Intelligence</span>
            </div>
        </div>
        <div class="ops-footer-meta">
            <span>© {{ now()->year }} Gematrix Ops</span>
            <span>Signal Integrity Layer Active</span>
        </div>
    </div>
</footer>

<script>
document.addEventListener('keydown', (e) => {
  if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
    e.preventDefault();
    const input = document.getElementById('phraseInput');
    if (input) input.focus();
  }
});

const input = document.getElementById('phraseInput');
const suggestions = document.getElementById('suggestions');
let timer;
if (input && suggestions) {
  input.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (q.length < 2) {
      suggestions.innerHTML = '';
      return;
    }

    timer = setTimeout(async () => {
      const res = await fetch(`/api/suggest?q=${encodeURIComponent(q)}`);
      if (!res.ok) return;
      const items = await res.json();
      suggestions.innerHTML = items.map(item => `<button type="button" class="suggestion-item" data-value="${item}">${item}</button>`).join('');
      suggestions.querySelectorAll('.suggestion-item').forEach(btn => btn.addEventListener('click', () => {
        input.value = btn.dataset.value;
        suggestions.innerHTML = '';
      }));
    }, 220);
  });
}

document.querySelectorAll('tr.row-link').forEach((row) => {
  row.addEventListener('click', (e) => {
    const interactive = e.target.closest('a, button, input, select, textarea, form, label');
    if (interactive) {
      return;
    }

    const href = row.dataset.href;
    if (href) {
      window.location.href = href;
    }
  });
});
</script>
</body>
</html>
