@php
    $explorerMeta = [
        'title' => 'Gematria Explorer - Gematrix',
        'description' => 'Browse indexed phrases, filter by cipher and value, and open detailed match analysis pages in Gematrix.',
        'canonical' => url('/explorer'),
        'robots' => 'noindex,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1',
        'og_type' => 'website',
        'json_ld' => [
            [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => 'Gematria Explorer',
                'url' => url('/explorer'),
                'description' => 'Filtered exploration of gematria phrase values.',
            ],
        ],
    ];
@endphp
<x-layouts.app :title="'Explorer'" :meta="$explorerMeta">
    <section class="card reveal">
        <div class="split-head">
            <h1>{{ __('messages.explorer') }}</h1>
            <p class="subtitle">{{ $phrases->total() }} entries found
                @if($phrases->count() > 0)
                    ({{ $phrases->firstItem() }}-{{ $phrases->lastItem() }})
                @endif
            </p>
        </div>
        <div class="stats-row">
            <div><i class="fa-solid fa-fingerprint"></i> Analysis ID: <code>{{ $analysisId }}</code></div>
            <div><i class="fa-solid fa-clock"></i> Dataset refresh: <strong>{{ optional($datasetFreshness)?->format('Y-m-d H:i') ?? 'n/a' }}</strong></div>
        </div>

        <form class="filters explorer-filters explorer-filters-grid" method="GET" id="explorerFilters">
            <input class="input input-bordered input-sm" name="phrase" placeholder="{{ __('messages.phrase') }}" value="{{ $filters['phrase'] }}">
            <select class="select select-bordered select-sm" name="theme">
                <option value="">All themes</option>
                @foreach($themes as $themeKey => $themeLabel)
                    <option value="{{ $themeKey }}" @selected(($filters['theme'] ?? '') === $themeKey)>{{ $themeLabel }}</option>
                @endforeach
            </select>
            <select class="select select-bordered select-sm" name="cipher">
                @foreach($cipherLabels as $key => $label)
                    <option value="{{ $key }}" @selected($filters['cipher'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <input class="input input-bordered input-sm" name="value" type="number" value="{{ $filters['value'] }}" placeholder="Value">
            <input class="input input-bordered input-sm" name="min_views" type="number" value="{{ $filters['min_views'] ?? 0 }}" placeholder="Min views">
            <input class="input input-bordered input-sm" name="min_searches" type="number" value="{{ $filters['min_searches'] ?? 0 }}" placeholder="Min searches">
            <select class="select select-bordered select-sm" name="sort">
                <option value="created_at" @selected($filters['sort'] === 'created_at')>Created</option>
                <option value="phrase" @selected($filters['sort'] === 'phrase')>{{ __('messages.phrase') }}</option>
                @foreach($cipherLabels as $key => $label)
                    <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="select select-bordered select-sm" name="dir">
                <option value="desc" @selected($filters['dir'] === 'desc')>DESC</option>
                <option value="asc" @selected($filters['dir'] === 'asc')>ASC</option>
            </select>
            <select class="select select-bordered select-sm" name="per_page">
                <option value="25" @selected((string)$filters['per_page'] === '25')>25 / page</option>
                <option value="50" @selected((string)$filters['per_page'] === '50')>50 / page</option>
                <option value="100" @selected((string)$filters['per_page'] === '100')>100 / page</option>
            </select>
            <button class="btn btn-primary btn-sm">{{ __('messages.filter') }}</button>
            <a class="btn btn-outline btn-sm" href="/explorer">Reset</a>
            <button class="btn btn-outline btn-sm" id="savePresetBtn" type="button">Save Preset</button>
        </form>
        <div class="chips mt-2" id="presetChips"></div>
    </section>

    <section class="card reveal">
        <div class="table-wrap">
            <table class="table table-zebra explorer-table">
                <thead>
                <tr>
                    <th class="phrase-col">{{ __('messages.phrase') }}</th>
                    @foreach($cipherLabels as $label)
                        <th>{{ $label }}</th>
                    @endforeach
                    <th>Views</th>
                    <th>Searches</th>
                    <th>Theme Hint</th>
                </tr>
                </thead>
                <tbody>
                @forelse($phrases as $phrase)
                    @php
                        $explorerLink = '/entry/'.$phrase->id.'?source=explorer'.($filters['phrase'] !== '' ? '&search_query='.urlencode($filters['phrase']) : '');
                    @endphp
                    <tr class="row-link" data-href="{{ $explorerLink }}">
                        <td class="phrase phrase-col"><a class="muted-link" href="{{ $explorerLink }}">{{ $phrase->phrase }}</a></td>
                        @foreach(array_keys($cipherLabels) as $key)
                            <td>{{ $phrase->{$key} }}</td>
                        @endforeach
                        <td>{{ number_format((int) $phrase->view_count) }}</td>
                        <td>{{ number_format((int) ($phrase->search_hits ?? $searchCounts[mb_strtolower($phrase->phrase)] ?? 0)) }}</td>
                        <td>
                            @php
                                $themeHint = 'General';
                                foreach($themes as $themeKey => $themeLabel) {
                                    foreach(config('gematria.theme_keywords.'.$themeKey, []) as $keyword) {
                                        if (str_contains(mb_strtolower($phrase->phrase), $keyword)) {
                                            $themeHint = $themeLabel;
                                            break 2;
                                        }
                                    }
                                }
                            @endphp
                            <span class="chip-link">{{ $themeHint }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="21">No entries found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($phrases->lastPage() > 1)
            @php
                $start = max(1, $phrases->currentPage() - 2);
                $end = min($phrases->lastPage(), $phrases->currentPage() + 2);
            @endphp
            <nav class="pager clean-pager" aria-label="Pagination">
                @if($phrases->onFirstPage())
                    <span class="page-btn disabled">Prev</span>
                @else
                    <a class="page-btn" href="{{ $phrases->previousPageUrl() }}">Prev</a>
                @endif

                @for($page = $start; $page <= $end; $page++)
                    <a class="page-btn @if($page === $phrases->currentPage()) active @endif" href="{{ $phrases->url($page) }}">{{ $page }}</a>
                @endfor

                @if($phrases->hasMorePages())
                    <a class="page-btn" href="{{ $phrases->nextPageUrl() }}">Next</a>
                @else
                    <span class="page-btn disabled">Next</span>
                @endif

                <form class="jump-form" action="/explorer" method="GET">
                    <input type="hidden" name="phrase" value="{{ $filters['phrase'] }}">
                    <input type="hidden" name="theme" value="{{ $filters['theme'] ?? '' }}">
                    <input type="hidden" name="cipher" value="{{ $filters['cipher'] }}">
                    <input type="hidden" name="value" value="{{ $filters['value'] }}">
                    <input type="hidden" name="min_views" value="{{ $filters['min_views'] ?? 0 }}">
                    <input type="hidden" name="min_searches" value="{{ $filters['min_searches'] ?? 0 }}">
                    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                    <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
                    <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">
                    <input class="input input-bordered input-xs" type="number" min="1" max="{{ $phrases->lastPage() }}" name="page" value="{{ $phrases->currentPage() }}" aria-label="Page">
                    <button class="btn btn-xs btn-primary" type="submit">Go</button>
                </form>
            </nav>
        @endif
    </section>
</x-layouts.app>

<script>
(() => {
  const form = document.getElementById('explorerFilters');
  const saveBtn = document.getElementById('savePresetBtn');
  const presetChips = document.getElementById('presetChips');
  if (!form || !saveBtn || !presetChips) return;

  const loadPresets = () => {
    try {
      return JSON.parse(localStorage.getItem('gematrix_explorer_presets') || '[]');
    } catch (_) {
      return [];
    }
  };
  const storePresets = (items) => localStorage.setItem('gematrix_explorer_presets', JSON.stringify(items.slice(0, 8)));

  const renderPresets = () => {
    const presets = loadPresets();
    if (!presets.length) {
      presetChips.innerHTML = '<span class="empty">No saved presets yet.</span>';
      return;
    }
    presetChips.innerHTML = presets.map((preset, idx) => `
      <button type="button" class="chip-link preset-load" data-index="${idx}">${preset.name}</button>
    `).join('');
    presetChips.querySelectorAll('.preset-load').forEach((btn) => {
      btn.addEventListener('click', () => {
        const item = presets[Number(btn.dataset.index)];
        if (!item?.params) return;
        const target = new URL('/explorer', window.location.origin);
        Object.entries(item.params).forEach(([k, v]) => {
          if (String(v) !== '') target.searchParams.set(k, String(v));
        });
        window.location.href = target.pathname + target.search;
      });
    });
  };

  saveBtn.addEventListener('click', () => {
    const name = prompt('Preset name');
    if (!name) return;
    const fd = new FormData(form);
    const params = {};
    fd.forEach((value, key) => { params[key] = String(value); });
    const presets = loadPresets();
    presets.unshift({ name: name.slice(0, 40), params, created_at: Date.now() });
    storePresets(presets);
    renderPresets();
  });

  renderPresets();
})();
</script>
