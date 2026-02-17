@php
    $highAnomalyCount = collect($anomalies ?? [])->filter(static fn ($item): bool => (($item['anomaly_level'] ?? '') === 'high'))->count();
    $trendSpike = $trendLens['spike_percent'] ?? null;
    $calculatorMeta = [
        'title' => 'Gematria Calculator - Gematrix',
        'description' => 'Calculate gematria values with multiple ciphers and discover matching phrases instantly in Gematrix.',
        'canonical' => url('/calculate'),
        'og_type' => 'website',
        'json_ld' => [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => 'Gematria Calculator',
                'url' => url('/calculate'),
                'description' => 'Interactive gematria calculator with phrase matching and cipher guide.',
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'Dataset',
                'name' => 'Gematrix Phrase Index',
                'description' => 'Indexed phrase totals across multiple gematria ciphers.',
                'url' => url('/explorer'),
            ],
        ],
    ];
@endphp
<x-layouts.app :title="'Gematrix'" :meta="$calculatorMeta">
    <section class="hero card reveal">
        <div>
            <p class="kicker"><i class="fa-solid fa-wave-square"></i> {{ __('messages.kicker') }}</p>
            <h1>{{ __('messages.title') }}</h1>
            <p class="subtitle">{{ __('messages.subtitle') }}</p>
            <div class="stats-row">
                <div><i class="fa-solid fa-database"></i> {{ __('messages.total_phrases') }} <strong>{{ number_format($totalPhrases) }}</strong></div>
                <div><i class="fa-solid fa-bolt"></i> API: <code>/api/calculate</code></div>
                <div><i class="fa-solid fa-fingerprint"></i> Analysis ID: <code>{{ $analysisId }}</code></div>
                <div><i class="fa-solid fa-clock"></i> Dataset refresh: <strong>{{ optional($datasetFreshness)?->format('Y-m-d H:i') ?? 'n/a' }}</strong></div>
            </div>
            <div class="hero-actions">
                <a href="/random" class="btn btn-primary btn-sm"><i class="fa-solid fa-shuffle"></i> Surprise Me</a>
                <a href="/explorer" class="btn btn-outline btn-sm"><i class="fa-solid fa-compass"></i> Explorer</a>
                <button type="button" id="hotkeyHelp" class="btn btn-outline btn-sm"><i class="fa-solid fa-keyboard"></i> Hotkeys</button>
            </div>
        </div>
    </section>

    <section class="card reveal calc-panel-sticky">
        <form method="GET" class="stack-sm" id="calcForm">
            <label for="phraseInput">{{ __('messages.search_phrase') }}</label>
            <div class="input-row">
                <input class="input input-bordered w-full" id="phraseInput" name="q" value="{{ $query }}" maxlength="120" placeholder="matrix code" required>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-calculator"></i> {{ __('messages.calculate') }}</button>
            </div>
            <label for="compareInput">Compare phrases (comma or new line separated, up to 8)</label>
            <textarea class="textarea textarea-bordered w-full" id="compareInput" name="compare" rows="2" placeholder="computer, matrix code, artificial intelligence">{{ $compareInput }}</textarea>
            @if($exactMatchEntry)
                <a class="btn btn-outline btn-sm w-fit" href="/entry/{{ $exactMatchEntry->id }}?source=search&search_query={{ urlencode($query) }}">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open detail page for "{{ $exactMatchEntry->phrase }}"
                </a>
            @endif
            <div id="suggestions" class="suggestions"></div>

            <details>
                <summary>{{ __('messages.cipher_selection') }}</summary>
                <div class="chips">
                    @foreach($enabledCiphers as $cipher)
                        <label class="chip">
                            <input class="cipher-checkbox" type="checkbox" name="ciphers[]" value="{{ $cipher }}" @checked(in_array($cipher, $selectedCiphers, true))>
                            <span>{{ $cipherLabels[$cipher] }}</span>
                        </label>
                    @endforeach
                </div>
            </details>
        </form>

        <div class="chips mt-2">
            <button type="button" class="btn btn-outline btn-sm" id="cipherAllBtn">All Ciphers</button>
            <button type="button" class="btn btn-outline btn-sm" id="cipherNoneBtn">No Ciphers</button>
            <button type="button" class="btn btn-outline btn-sm" id="cipherResetBtn">Reset Selection</button>
            <button type="button" class="btn btn-outline btn-sm" id="cipherSavePresetBtn">Save Cipher Preset</button>
            <button type="button" class="btn btn-outline btn-sm" id="compactToggleBtn">Compact View</button>
            <span class="chip-link">Visible matches: <strong id="visibleMatchesCount">{{ count($matches) }}</strong></span>
        </div>
        <div class="chips mt-2" id="cipherPresetChips"></div>

        <div class="score-grid">
            @foreach($scores as $key => $value)
                <article class="score-card pop-in cipher-score-card @if(in_array($key, $selectedCiphers, true)) active-cipher @endif" data-cipher="{{ $key }}">
                    <p>{{ $cipherLabels[$key] }}</p>
                    <h2>{{ $value }}</h2>
                    <p class="legend-desc">Matches: <strong class="cipher-match-count" data-cipher-count="{{ $key }}">0</strong></p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="card reveal">
        <details>
            <summary><i class="fa-solid fa-circle-info"></i> Additional Information (Advanced Modules)</summary>
            <p class="subtitle mt-2">Expanded research panels are grouped here to keep the calculate view focused.
                <span class="chip-link">High anomalies: {{ $highAnomalyCount }}</span>
                <span class="chip-link">
                    Trend:
                    @if($trendSpike === null)
                        new
                    @else
                        {{ $trendSpike >= 0 ? '+' : '' }}{{ number_format((float) $trendSpike, 2) }}%
                    @endif
                </span>
            </p>

            <div class="stack-sm mt-3">
    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-code-compare"></i> Live Compare Board</h3>
            <p class="subtitle">Compare multiple phrases side by side with direct jump to detail pages.</p>
        </div>
        <div class="table-wrap">
            <table class="table table-zebra">
                <thead>
                <tr>
                    <th>Phrase</th>
                    @foreach($scores as $cipher => $value)
                        <th>{{ $cipherLabels[$cipher] }}</th>
                    @endforeach
                    <th>Open</th>
                </tr>
                </thead>
                <tbody>
                @forelse($compareRows as $row)
                    <tr>
                        <td class="phrase">{{ $row['phrase'] }}</td>
                        @foreach($scores as $cipher => $value)
                            <td>{{ $row['scores'][$cipher] ?? '-' }}</td>
                        @endforeach
                        <td>
                            @if($row['entry_id'])
                                <a class="muted-link" href="/entry/{{ $row['entry_id'] }}?source=compare&search_query={{ urlencode($query) }}">Detail</a>
                            @else
                                <a class="muted-link" href="/calculate?q={{ urlencode($row['phrase']) }}">Calculate</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="30">Add compare phrases above to activate this board.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-book-open"></i> Cipher Guide</h3>
            <p class="subtitle">Short meaning and logic for each cipher</p>
        </div>
        <div class="legend-grid">
            @foreach($scores as $key => $value)
                <article class="legend-card">
                    <p class="legend-title">
                        <i class="{{ $cipherIcons[$key] ?? 'fa-solid fa-calculator' }}"></i>
                        {{ $cipherLabels[$key] }}
                    </p>
                    <p class="legend-desc">{{ $cipherDescriptions[$key] ?? '' }}</p>
                    <p class="legend-value">Current: <strong>{{ $value }}</strong></p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-table-cells-large"></i> Cross-Cipher Heatmap</h3>
            <p class="subtitle">Top cipher pair combinations found in current match set.</p>
        </div>
        <div class="table-wrap">
            <table class="table table-zebra">
                <thead><tr><th>Cipher A</th><th>Cipher B</th><th>Co-Matches</th></tr></thead>
                <tbody>
                @forelse($heatmap as $cell)
                    <tr>
                        <td>{{ $cipherLabels[$cell['left']] ?? $cell['left'] }}</td>
                        <td>{{ $cipherLabels[$cell['right']] ?? $cell['right'] }}</td>
                        <td>{{ number_format((int) $cell['count']) }}</td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="3">No heatmap data yet for this query.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-puzzle-piece"></i> N-Gram Subphrase Decoder</h3>
            <p class="subtitle">Token-level and phrase-level decomposition to expose hidden structures inside the input.</p>
        </div>
        <div class="table-wrap">
            <table class="table table-zebra">
                <thead>
                <tr><th>Subphrase</th><th>English</th><th>Simple</th><th>Matching Phrases</th><th>Open</th></tr>
                </thead>
                <tbody>
                @forelse($subphraseDecode as $item)
                    <tr>
                        <td class="phrase">{{ $item['text'] }}</td>
                        <td>{{ $item['scores']['english_gematria'] ?? '-' }}</td>
                        <td>{{ $item['scores']['simple_gematria'] ?? '-' }}</td>
                        <td>{{ number_format((int) $item['matches']) }}</td>
                        <td>
                            @if($item['exact_id'])
                                <a class="muted-link" href="/entry/{{ $item['exact_id'] }}?source=subphrase&search_query={{ urlencode($query) }}">Detail</a>
                            @else
                                <a class="muted-link" href="/calculate?q={{ urlencode($item['text']) }}">Calculate</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="5">No subphrase data available.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-triangle-exclamation"></i> Anomaly Detector</h3>
            <p class="subtitle">Rare values indicate potentially strong symbolic outliers across the dataset.</p>
        </div>
        <div class="table-wrap">
            <table class="table table-zebra">
                <thead><tr><th>Cipher</th><th>Value</th><th>Frequency</th><th>Rarity Score</th><th>Level</th></tr></thead>
                <tbody>
                @foreach($anomalies as $signal)
                    <tr>
                        <td>{{ $cipherLabels[$signal['cipher']] ?? $signal['cipher'] }}</td>
                        <td>{{ $signal['value'] }}</td>
                        <td>{{ number_format((int) $signal['frequency']) }}</td>
                        <td>{{ number_format((float) $signal['rarity_score'], 2) }}</td>
                        <td><span class="chip-link">{{ strtoupper($signal['anomaly_level']) }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-wave-square"></i> Temporal Trend Lens</h3>
            <p class="subtitle">Query momentum over time with 7-day spike diagnostics.</p>
        </div>
        <div class="stats-row">
            <div>Last 7d: <strong>{{ number_format((int) $trendLens['current7']) }}</strong></div>
            <div>Prev 7d: <strong>{{ number_format((int) $trendLens['previous7']) }}</strong></div>
            <div>Spike:
                <strong>
                    @if($trendLens['spike_percent'] === null)
                        New / no baseline
                    @else
                        {{ $trendLens['spike_percent'] >= 0 ? '+' : '' }}{{ number_format((float) $trendLens['spike_percent'], 2) }}%
                    @endif
                </strong>
            </div>
        </div>
        <div class="trend-bars mt-3">
            @forelse($trendLens['daily'] as $day)
                @php $width = min(100, (int) round(($day['aggregate'] / max(1, (int) $trendLens['max_daily'])) * 100)); @endphp
                <div class="trend-row">
                    <span>{{ $day['day'] }}</span>
                    <div class="trend-meter"><div style="width: {{ $width }}%"></div></div>
                    <strong>{{ $day['aggregate'] }}</strong>
                </div>
            @empty
                <p class="empty">No trend history for this query yet.</p>
            @endforelse
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-layer-group"></i> Theme / Corpus Matching</h3>
            <p class="subtitle">Matches grouped by semantic domains for faster contextual decoding.</p>
        </div>
        <div class="legend-grid">
            @forelse($themeInsights as $theme)
                <article class="legend-card">
                    <p class="legend-title">{{ $theme['label'] }}</p>
                    <p class="legend-desc">{{ number_format((int) $theme['count']) }} thematic matches found.</p>
                    <div class="chips mt-2">
                        @foreach($theme['top'] as $top)
                            <a class="chip-link" href="/entry/{{ $top->id }}?source=theme&search_query={{ urlencode($query) }}">{{ $top->phrase }}</a>
                        @endforeach
                    </div>
                    <a class="muted-link" href="/explorer?theme={{ urlencode($theme['key']) }}">Open {{ $theme['label'] }} Explorer</a>
                </article>
            @empty
                <p class="empty">No theme matches available yet.</p>
            @endforelse
        </div>
    </section>

    <section class="card reveal" id="hybridDecodePanel">
        <div class="split-head">
            <h3><i class="fa-solid fa-brain"></i> Hybrid AI + Gematria Search</h3>
            <p class="subtitle">Combines lexical similarity with multi-cipher overlap for stronger discovery.</p>
        </div>
        <form id="hybridSearchForm" class="filters explorer-filters" method="GET" action="/calculate">
            <input class="input input-bordered input-sm" name="hybrid_q" id="hybridQuery" value="{{ $query }}" maxlength="120">
            <select class="select select-bordered select-sm" id="hybridLimit" name="hybrid_limit">
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="30">30</option>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">Run Hybrid Decode</button>
        </form>
        <div class="table-wrap mt-3">
            <table class="table table-zebra">
                <thead><tr><th>Phrase</th><th>Hybrid</th><th>Lexical</th><th>Cipher</th><th>Shared</th><th>Theme</th><th>Open</th></tr></thead>
                <tbody id="hybridResultsBody">
                    <tr><td colspan="7" class="empty">Run hybrid decode to load results.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
            </div>
        </details>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-link"></i> {{ __('messages.matching_phrases') }}</h3>
            <a class="muted-link" href="/explorer">{{ __('messages.open_explorer') }}</a>
        </div>
        <div class="table-wrap">
            <table class="table table-zebra calc-matches-table">
                <thead>
                <tr>
                    <th>{{ __('messages.phrase') }}</th>
                    @foreach($scores as $cipher => $value)
                        <th>{{ $cipherLabels[$cipher] }}</th>
                    @endforeach
                    <th>{{ __('messages.hits') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($matches as $match)
                    <tr class="match-row" data-hit-ciphers="{{ implode(',', $match['hits']) }}">
                        <td class="phrase"><a class="muted-link" href="/entry/{{ $match['id'] }}?source=search&search_query={{ urlencode($query) }}">{{ $match['phrase'] }}</a></td>
                        @foreach($scores as $cipher => $value)
                            <td class="@if(in_array($cipher, $match['hits'], true)) match-cell @endif">
                                {{ $match['scores'][$cipher] }}
                            </td>
                        @endforeach
                        <td>
                            <div class="badges">
                                @foreach($match['hits'] as $hit)
                                    <span>{{ $cipherLabels[$hit] }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            @auth
                                @if(in_array($match['phrase'], $favorites, true))
                                    <form method="POST" action="/workspace/favorite">@csrf @method('DELETE')<input type="hidden" name="phrase" value="{{ $match['phrase'] }}"><button class="ghost-btn"><i class="fa-solid fa-star"></i></button></form>
                                @else
                                    <form method="POST" action="/workspace/favorite">@csrf <input type="hidden" name="phrase" value="{{ $match['phrase'] }}"><button class="ghost-btn"><i class="fa-regular fa-star"></i></button></form>
                                @endif
                            @endauth
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="20" class="empty">{{ __('messages.no_results') }}</td></tr>
                @endforelse
                <tr id="noMatchesRow" style="display:none;"><td colspan="20" class="empty">No matches visible for the current cipher filter.</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-fire"></i> Trending Searches (14 days)</h3>
        </div>
        <div class="chips">
            @forelse($trending as $item)
                <a href="/calculate?q={{ urlencode($item->query_norm) }}" class="chip-link">
                    {{ $item->query_norm }}
                    <span class="badge badge-primary badge-sm">{{ number_format((int) $item->aggregate) }}</span>
                </a>
            @empty
                <p class="empty">No trend data available yet.</p>
            @endforelse
        </div>
    </section>

    <section class="card reveal">
        <div class="split-head">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Query Sessions (7d)</h3>
            <p class="subtitle">Fast re-run list for operator workflow.</p>
        </div>
        <div class="chips">
            @forelse($recentQueries as $rq)
                <a class="chip-link" href="/calculate?q={{ urlencode($rq->query_norm) }}">{{ $rq->query_norm }} <span class="badge badge-primary badge-sm">{{ number_format((int) $rq->aggregate) }}</span></a>
            @empty
                <span class="empty">No recent sessions available.</span>
            @endforelse
        </div>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-clock-rotate-left"></i> {{ __('messages.latest_entries') }}</h3>
        <div class="chips">
            @foreach($latest as $item)
                <a href="/entry/{{ $item->id }}?source=latest" class="chip-link">{{ $item->phrase }}</a>
            @endforeach
        </div>
        @if($latestCount === 0)
            <p class="empty">No entries available yet. Run `docker compose exec -T app php artisan db:seed` once.</p>
        @endif
    </section>
</x-layouts.app>

<script>
(() => {
  const storageKeyPreset = 'gematrix_cipher_presets_v1';
  const storageKeyCompact = 'gematrix_compact_mode_v1';

  const hotkeyHelp = document.getElementById('hotkeyHelp');
  if (hotkeyHelp) {
    hotkeyHelp.addEventListener('click', () => {
      alert('Hotkeys:\\n/ focus search\\nEnter calculate\\nShift+Enter open top match\\nCtrl+K open explorer\\nAlt+ArrowRight next cipher\\nAlt+ArrowLeft previous cipher\\nAlt+S save cipher preset\\nAlt+C toggle compact view');
    });
  }

  const calcForm = document.getElementById('calcForm');
  const cipherCheckboxes = Array.from(document.querySelectorAll('.cipher-checkbox'));
  const cipherCards = Array.from(document.querySelectorAll('.cipher-score-card'));
  const matchRows = Array.from(document.querySelectorAll('.match-row'));
  const visibleMatchesCount = document.getElementById('visibleMatchesCount');
  const noMatchesRow = document.getElementById('noMatchesRow');
  const allBtn = document.getElementById('cipherAllBtn');
  const noneBtn = document.getElementById('cipherNoneBtn');
  const resetBtn = document.getElementById('cipherResetBtn');
  const savePresetBtn = document.getElementById('cipherSavePresetBtn');
  const presetChips = document.getElementById('cipherPresetChips');
  const compactToggleBtn = document.getElementById('compactToggleBtn');
  const defaultSelected = new Set(cipherCheckboxes.filter((cb) => cb.checked).map((cb) => cb.value));

  const updateCipherCardsActive = () => {
    const active = new Set(cipherCheckboxes.filter((cb) => cb.checked).map((cb) => cb.value));
    cipherCards.forEach((card) => {
      card.classList.toggle('active-cipher', active.has(card.dataset.cipher));
    });
    return active;
  };

  const updateMatchFilters = () => {
    const active = updateCipherCardsActive();
    let visible = 0;
    const cipherCounts = {};

    matchRows.forEach((row) => {
      const hits = String(row.dataset.hitCiphers || '').split(',').filter(Boolean);
      const hitSet = new Set(hits);
      const shouldShow = active.size === 0 ? true : [...active].some((cipher) => hitSet.has(cipher));
      row.style.display = shouldShow ? '' : 'none';
      if (shouldShow) {
        visible += 1;
        hits.forEach((cipher) => {
          cipherCounts[cipher] = (cipherCounts[cipher] || 0) + 1;
        });
      }
    });

    if (visibleMatchesCount) {
      visibleMatchesCount.textContent = String(visible);
    }

    const noVisible = visible === 0;
    if (noMatchesRow) {
      noMatchesRow.style.display = noVisible ? '' : 'none';
      noMatchesRow.textContent = noVisible ? 'No matches visible for the current cipher filter.' : '';
    }

    document.querySelectorAll('.cipher-match-count').forEach((el) => {
      const key = el.getAttribute('data-cipher-count') || '';
      el.textContent = String(cipherCounts[key] || 0);
    });
  };

  const setAllCiphers = (checked) => {
    cipherCheckboxes.forEach((cb) => { cb.checked = checked; });
    updateMatchFilters();
  };

  const restoreDefaultCiphers = () => {
    cipherCheckboxes.forEach((cb) => { cb.checked = defaultSelected.has(cb.value); });
    updateMatchFilters();
  };

  const readPresets = () => {
    try {
      const raw = localStorage.getItem(storageKeyPreset);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (_) {
      return [];
    }
  };

  const writePresets = (items) => {
    localStorage.setItem(storageKeyPreset, JSON.stringify(items.slice(0, 8)));
  };

  const applyPreset = (cipherList) => {
    const set = new Set(cipherList);
    cipherCheckboxes.forEach((cb) => {
      cb.checked = set.has(cb.value);
    });
    updateMatchFilters();
  };

  const renderPresetChips = () => {
    if (!presetChips) return;
    const presets = readPresets();
    if (presets.length === 0) {
      presetChips.innerHTML = '<span class="empty">No cipher presets saved yet.</span>';
      return;
    }
    presetChips.innerHTML = presets.map((preset, idx) => `
      <button type="button" class="chip-link cipher-preset-load" data-index="${idx}">${preset.name}</button>
    `).join('');
    presetChips.querySelectorAll('.cipher-preset-load').forEach((btn) => {
      btn.addEventListener('click', () => {
        const item = presets[Number(btn.dataset.index)];
        if (!item?.ciphers) return;
        applyPreset(item.ciphers);
      });
    });
  };

  const saveCurrentPreset = () => {
    const selected = cipherCheckboxes.filter((cb) => cb.checked).map((cb) => cb.value);
    const name = prompt('Cipher preset name');
    if (!name) return;
    const presets = readPresets();
    presets.unshift({
      name: name.slice(0, 36),
      ciphers: selected,
      created_at: Date.now(),
    });
    writePresets(presets);
    renderPresetChips();
  };

  const applyCompactMode = (enabled) => {
    document.body.classList.toggle('compact-mode', enabled);
    if (compactToggleBtn) {
      compactToggleBtn.textContent = enabled ? 'Comfort View' : 'Compact View';
    }
    localStorage.setItem(storageKeyCompact, enabled ? '1' : '0');
  };

  if (allBtn) allBtn.addEventListener('click', () => setAllCiphers(true));
  if (noneBtn) noneBtn.addEventListener('click', () => setAllCiphers(false));
  if (resetBtn) resetBtn.addEventListener('click', restoreDefaultCiphers);
  if (savePresetBtn) savePresetBtn.addEventListener('click', saveCurrentPreset);
  if (compactToggleBtn) {
    compactToggleBtn.addEventListener('click', () => {
      applyCompactMode(!document.body.classList.contains('compact-mode'));
    });
  }

  cipherCheckboxes.forEach((cb) => cb.addEventListener('change', updateMatchFilters));
  cipherCards.forEach((card) => {
    card.addEventListener('click', () => {
      const key = card.dataset.cipher;
      const input = cipherCheckboxes.find((cb) => cb.value === key);
      if (!input) return;
      input.checked = !input.checked;
      updateMatchFilters();
    });
  });

  renderPresetChips();
  updateMatchFilters();
  applyCompactMode(localStorage.getItem(storageKeyCompact) === '1');

  document.addEventListener('keydown', (event) => {
    if (event.altKey && !event.ctrlKey && !event.metaKey && event.key.toLowerCase() === 's') {
      event.preventDefault();
      saveCurrentPreset();
    }
    if (event.altKey && !event.ctrlKey && !event.metaKey && event.key.toLowerCase() === 'c') {
      event.preventDefault();
      applyCompactMode(!document.body.classList.contains('compact-mode'));
    }
    if (event.altKey && (event.key === 'ArrowRight' || event.key === 'ArrowLeft')) {
      event.preventDefault();
      if (cipherCheckboxes.length > 0) {
        const values = cipherCheckboxes.map((cb) => cb.value);
        const active = new Set(cipherCheckboxes.filter((cb) => cb.checked).map((cb) => cb.value));
        const anchor = values.findIndex((v) => active.has(v));
        const base = anchor === -1 ? 0 : anchor;
        const next = event.key === 'ArrowRight'
          ? (base + 1) % values.length
          : (base - 1 + values.length) % values.length;
        setAllCiphers(false);
        const target = cipherCheckboxes[next];
        target.checked = true;
        updateMatchFilters();
      }
    }
    if (event.key === 'Enter' && event.shiftKey) {
      const top = document.querySelector('section .table tbody tr td.phrase a.muted-link');
      if (top) {
        event.preventDefault();
        window.location.href = top.getAttribute('href');
      }
    }
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      window.location.href = '/explorer';
    }
  });

  const form = document.getElementById('hybridSearchForm');
  const queryInput = document.getElementById('hybridQuery');
  const limitInput = document.getElementById('hybridLimit');
  const body = document.getElementById('hybridResultsBody');
  if (!form || !queryInput || !limitInput || !body) return;

  const renderRows = (items) => {
    if (!Array.isArray(items) || items.length === 0) {
      body.innerHTML = '<tr><td colspan="7" class="empty">No hybrid matches found.</td></tr>';
      return;
    }
    body.innerHTML = items.map((item) => `
      <tr>
        <td class="phrase">${item.phrase}</td>
        <td>${item.hybrid_score}</td>
        <td>${item.lexical_similarity}%</td>
        <td>${item.cipher_similarity}%</td>
        <td>${item.shared_ciphers}</td>
        <td>${item.theme}</td>
        <td><a class="muted-link" href="${item.url}">Open</a></td>
      </tr>
    `).join('');
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const q = queryInput.value.trim();
    if (q.length < 2) return;
    body.innerHTML = `
      <tr><td colspan="7"><div class="skeleton-row"></div></td></tr>
      <tr><td colspan="7"><div class="skeleton-row"></div></td></tr>
      <tr><td colspan="7"><div class="skeleton-row"></div></td></tr>
    `;

    try {
      const res = await fetch(`/api/hybrid-search?q=${encodeURIComponent(q)}&limit=${encodeURIComponent(limitInput.value)}`);
      if (!res.ok) {
        body.innerHTML = '<tr><td colspan="7" class="empty">Hybrid API unavailable.</td></tr>';
        return;
      }
      const payload = await res.json();
      renderRows(payload.results || []);
    } catch (_) {
      body.innerHTML = '<tr><td colspan="7" class="empty">Hybrid API failed.</td></tr>';
    }
  });
})();
</script>
