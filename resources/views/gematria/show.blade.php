@php
    $entryUrl = url('/entry/'.$entry->id);
    $entryTitle = $entry->phrase.' - '.$cipherLabels[$cipher].' '.$value.' | Gematrix';
    $entryDescription = 'Gematria detail for "'.$entry->phrase.'": full cipher totals, letter-by-letter breakdown, and ranked phrase connections.';
    $rankedPreview = $connections->getCollection()->take(10)->values();
    $entryMeta = [
        'title' => $entryTitle,
        'description' => $entryDescription,
        'canonical' => $entryUrl,
        'og_type' => 'article',
        'json_ld' => [
            [
                '@context' => 'https://schema.org',
                '@type' => 'DefinedTerm',
                'name' => $entry->phrase,
                'description' => $entryDescription,
                'url' => $entryUrl,
                'inDefinedTermSet' => url('/explorer'),
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => 'Gematria matches for '.$entry->phrase,
                'itemListElement' => $rankedPreview->map(static fn ($item, $idx): array => [
                    '@type' => 'ListItem',
                    'position' => $idx + 1,
                    'name' => $item->phrase,
                    'url' => url('/entry/'.$item->id),
                ])->all(),
            ],
        ],
    ];
@endphp
<x-layouts.app :title="$entry->phrase" :meta="$entryMeta">
    <section class="card reveal detail-hero">
        <div>
            <p class="kicker"><i class="fa-solid fa-circle-nodes"></i> Cipher Detail</p>
            <h1>{{ $entry->phrase }}</h1>
            <p class="subtitle">Entry #{{ $entry->id }}. Explore all cipher values, calculation logic, and direct connections.</p>
            <div class="stats-row">
                <div><i class="fa-solid fa-eye"></i> Views: <strong>{{ number_format((int) $entry->view_count) }}</strong></div>
                <div><i class="fa-solid fa-magnifying-glass"></i> Searches: <strong>{{ number_format($entrySearchCount) }}</strong></div>
                <div><i class="fa-solid fa-fingerprint"></i> Analysis ID: <code>{{ $analysisId }}</code></div>
                <div><i class="fa-solid fa-clock"></i> Dataset refresh: <strong>{{ optional($datasetFreshness)?->format('Y-m-d H:i') ?? 'n/a' }}</strong></div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn btn-primary btn-sm" href="/calculate?q={{ urlencode($entry->phrase) }}"><i class="fa-solid fa-calculator"></i> Calculate</a>
            <a class="btn btn-outline btn-sm" href="/random"><i class="fa-solid fa-shuffle"></i> Random</a>
            <button class="btn btn-outline btn-sm" type="button" id="copyEntryLink"><i class="fa-solid fa-link"></i> Copy Link</button>
        </div>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-table-cells"></i> All Cipher Values</h3>
        <div class="score-grid">
            @foreach($allTotals as $key => $total)
                <a href="/entry/{{ $entry->id }}?cipher={{ $key }}" class="score-card @if($cipher === $key) active-cipher @endif">
                    <p>{{ $cipherLabels[$key] }}</p>
                    <h2>{{ $total }}</h2>
                    <p>{{ $cipherDetails[$key]['short'] ?? '' }}</p>
                </a>
            @endforeach
        </div>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-compass-drafting"></i> How does {{ $cipherLabels[$cipher] }} work?</h3>
        <p class="subtitle">{{ $cipherDescriptions[$cipher] ?? 'Cipher description is not available.' }}</p>
        <p class="subtitle"><strong>Logic:</strong> {{ $cipherDetails[$cipher]['short'] ?? '-' }}</p>
        <p class="subtitle"><strong>Formula:</strong> {{ $cipherDetails[$cipher]['formula'] ?? '-' }}</p>
        <p class="subtitle"><strong>Method:</strong> {{ $cipherDetails[$cipher]['method'] ?? '-' }}</p>
        <p class="subtitle"><strong>Example:</strong> {{ $cipherDetails[$cipher]['example'] ?? '-' }}</p>
        <p class="subtitle">Note: The breakdown below always follows the currently selected cipher. Pick another cipher above to switch instantly.</p>
        <p class="subtitle"><strong>Current total:</strong> {{ $value }}</p>

        <div class="formula-box">
            <strong>Breakdown:</strong>
            <code>
                @forelse($selectedBreakdown['items'] as $item)
                    {{ $item['char'] }}({{ $item['value'] }})@if(! $loop->last), @endif
                @empty
                    No letters detected.
                @endforelse
            </code>
            <p><strong>Sum:</strong> {{ $selectedBreakdown['expression'] !== '' ? $selectedBreakdown['expression'].' = '.$value : $value }}</p>
        </div>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-keyboard"></i> Letter Keyboard Visualizer</h3>
        <p class="subtitle">Each key shows its value in the selected cipher. Letters used in "{{ $entry->phrase }}" are highlighted.</p>
        @php
            $usedChars = array_map(static fn(array $item): string => $item['char'], $selectedBreakdown['items']);
            $rows = [
                range('A', 'J'),
                range('K', 'S'),
                range('T', 'Z'),
            ];
        @endphp
        <div class="kbd-visualizer">
            @foreach($rows as $row)
                <div class="kbd-row">
                    @foreach($row as $char)
                        @php
                            $index = ord($char) - 64;
                            $isUsed = in_array($char, $usedChars, true);
                        @endphp
                        <div class="kbd-key @if($isUsed) used @endif" data-char="{{ $char }}">
                            <span class="kbd-letter">{{ $char }}</span>
                            <span class="kbd-value">{{ $selectedCipherMap[$index] ?? 0 }}</span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="calc-animation">
            <div class="calc-controls">
                <button type="button" id="calcPlay"><i class="fa-solid fa-play"></i> Start animation</button>
                <button type="button" id="calcReset" class="ghost-btn"><i class="fa-solid fa-rotate-left"></i> Reset</button>
            </div>
            <div class="calc-track" id="calcTrack">
                @foreach($selectedBreakdown['items'] as $item)
                    <div class="calc-step" data-char="{{ $item['char'] }}" data-value="{{ $item['value'] }}">
                        <span>{{ $item['char'] }}</span>
                        <strong>{{ $item['value'] }}</strong>
                    </div>
                @endforeach
            </div>
            <p class="subtitle"><strong>Live sum:</strong> <span id="calcLiveSum">0</span> / {{ $value }}</p>
        </div>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-project-diagram"></i> Multi-Cipher Pattern Graph</h3>
        <p class="subtitle">Interactive 3D-style graph centered on this phrase. Use filters to reduce visual noise.</p>
        <div class="graph-controls">
            <label class="chip">
                <span>Min edge weight</span>
                <input type="range" id="graphMinWeight" min="1" max="10" value="2">
                <strong id="graphMinWeightValue">2</strong>
            </label>
            <label class="chip">
                <input type="checkbox" id="graphPairEdges" checked>
                <span>Show phrase-to-phrase edges</span>
            </label>
            <label class="chip">
                <input type="checkbox" id="graphOrbit">
                <span>Auto orbit</span>
            </label>
            <button type="button" class="btn btn-outline btn-sm" id="graphResetView"><i class="fa-solid fa-arrows-rotate"></i> Reset View</button>
            <span class="chip-link">Drag: rotate</span>
            <span class="chip-link">Wheel: zoom</span>
            <span class="chip-link">Nodes: {{ number_format((int) ($graphData['meta']['node_count'] ?? 0)) }}</span>
            <span class="chip-link">Edges: {{ number_format((int) ($graphData['meta']['edge_count'] ?? 0)) }}</span>
        </div>
        <div id="patternGraph" class="pattern-graph">
            <canvas id="patternGraphCanvas" width="960" height="460"></canvas>
        </div>
    </section>

    <section class="card reveal">
        <h3><i class="fa-solid fa-link"></i> Connections via {{ $cipherLabels[$cipher] }} = {{ $value }}</h3>
        <p class="subtitle">Rank profile: <strong>{{ $rankModeLabels[$rankMode] ?? 'Balanced' }}</strong>. Showing {{ number_format($connections->total()) }} total matches.</p>
        <form method="GET" action="/entry/{{ $entry->id }}" class="filters explorer-filters mt-3">
            <input type="hidden" name="cipher" value="{{ $cipher }}">
            <select class="select select-bordered select-sm" name="rank_mode">
                @foreach($rankModeLabels as $modeKey => $modeLabel)
                    <option value="{{ $modeKey }}" @selected($rankMode === $modeKey)>{{ $modeLabel }}</option>
                @endforeach
            </select>
            <select class="select select-bordered select-sm" name="per_page">
                <option value="100" @selected((string) $connectionPerPage === '100')>100 / page</option>
                <option value="250" @selected((string) $connectionPerPage === '250')>250 / page</option>
                <option value="500" @selected((string) $connectionPerPage === '500')>500 / page</option>
                <option value="1000" @selected((string) $connectionPerPage === '1000')>1000 / page</option>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">Apply Ranking</button>
        </form>
        <div class="table-wrap">
            <table class="table table-zebra detail-connections-table">
                <thead>
                <tr>
                    <th>Rank</th>
                    <th class="phrase-col">Phrase</th>
                    <th>{{ $cipherLabels[$cipher] }}</th>
                    <th>Shared Ciphers</th>
                    <th>Interesting Score</th>
                    <th>Confidence</th>
                    <th>Why this rank</th>
                    <th>Matched Ciphers</th>
                    <th>Theme</th>
                    <th>Views</th>
                    <th>Searches</th>
                </tr>
                </thead>
                <tbody>
                @forelse($connections as $item)
                    <tr>
                        <td>{{ number_format((int) ($connections->firstItem() + $loop->index)) }}</td>
                        <td class="phrase phrase-col"><a class="muted-link" href="/entry/{{ $item->id }}?cipher={{ $cipher }}&source=connection&search_query={{ urlencode($entry->phrase) }}">{{ $item->phrase }}</a></td>
                        <td class="match-cell">{{ $item->{$cipher} }}</td>
                        <td>{{ number_format((int) ($item->shared_cipher_hits ?? 0)) }}</td>
                        <td>{{ number_format((float) ($item->interesting_score ?? 0), 2) }}</td>
                        <td>
                            <div class="confidence-bar">
                                <div style="width: {{ max(0, min(100, (float) ($item->confidence_score ?? 0))) }}%"></div>
                            </div>
                            <span class="small-note">{{ number_format((float) ($item->confidence_score ?? 0), 2) }}</span>
                        </td>
                        <td class="small-note">{{ $item->score_explain }}</td>
                        <td class="small-note">
                            @if(!empty($item->matched_ciphers))
                                {{ collect($item->matched_ciphers)->map(fn($c) => $cipherLabels[$c] ?? $c)->implode(', ') }}
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $item->theme ?? 'General' }}</td>
                        <td>{{ number_format((int) $item->view_count) }}</td>
                        <td>{{ number_format((int) ($item->search_hits ?? 0)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="empty">No connections with the same value were found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($connections->lastPage() > 1)
            <nav class="pager clean-pager" aria-label="Connections Pagination">
                @if($connections->onFirstPage())
                    <span class="page-btn disabled">Prev</span>
                @else
                    <a class="page-btn" href="{{ $connections->previousPageUrl() }}">Prev</a>
                @endif

                @php
                    $start = max(1, $connections->currentPage() - 2);
                    $end = min($connections->lastPage(), $connections->currentPage() + 2);
                @endphp
                @for($page = $start; $page <= $end; $page++)
                    <a class="page-btn @if($page === $connections->currentPage()) active @endif" href="{{ $connections->url($page) }}">{{ $page }}</a>
                @endfor

                @if($connections->hasMorePages())
                    <a class="page-btn" href="{{ $connections->nextPageUrl() }}">Next</a>
                @else
                    <span class="page-btn disabled">Next</span>
                @endif
            </nav>
        @endif
    </section>
</x-layouts.app>

<script>
(() => {
  const playBtn = document.getElementById('calcPlay');
  const resetBtn = document.getElementById('calcReset');
  const copyBtn = document.getElementById('copyEntryLink');
  const graphRoot = document.getElementById('patternGraph');
  const live = document.getElementById('calcLiveSum');
  const steps = Array.from(document.querySelectorAll('#calcTrack .calc-step'));
  const keys = Array.from(document.querySelectorAll('.kbd-key[data-char]'));
  const keyMap = new Map(keys.map((key) => [key.dataset.char, key]));
  const graph = @json($graphData);

  if (copyBtn) {
    copyBtn.addEventListener('click', async () => {
      await navigator.clipboard.writeText(window.location.href);
      copyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
      setTimeout(() => {
        copyBtn.innerHTML = '<i class="fa-solid fa-link"></i> Copy Link';
      }, 1200);
    });
  }

  if (playBtn && resetBtn && live && steps.length > 0) {
    let timer = null;

    const reset = () => {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
      steps.forEach((step) => step.classList.remove('active', 'done'));
      keys.forEach((key) => key.classList.remove('active', 'done'));
      live.textContent = '0';
    };

    const play = () => {
      reset();
      let idx = 0;
      let sum = 0;
      timer = setInterval(() => {
        if (idx >= steps.length) {
          clearInterval(timer);
          timer = null;
          return;
        }
        const step = steps[idx];
        const char = step.dataset.char;
        const key = char ? keyMap.get(char) : null;
        const value = Number(step.dataset.value || '0');
        step.classList.add('active');
        if (key) {
          key.classList.add('active');
        }
        sum += value;
        live.textContent = String(sum);
        setTimeout(() => {
          step.classList.remove('active');
          step.classList.add('done');
          if (key) {
            key.classList.remove('active');
            key.classList.add('done');
          }
        }, 260);
        idx++;
      }, 380);
    };

    playBtn.addEventListener('click', play);
    resetBtn.addEventListener('click', reset);
  }

  if (graphRoot && graph?.nodes?.length) {
    const canvas = document.getElementById('patternGraphCanvas');
    const minWeightInput = document.getElementById('graphMinWeight');
    const minWeightValue = document.getElementById('graphMinWeightValue');
    const pairEdgeInput = document.getElementById('graphPairEdges');
    const orbitInput = document.getElementById('graphOrbit');
    const resetViewBtn = document.getElementById('graphResetView');
    if (canvas && minWeightInput && minWeightValue && pairEdgeInput && orbitInput) {
      const ctx = canvas.getContext('2d');
      if (!ctx) return;

      const rootNode = graph.nodes.find((n) => n.is_root) || graph.nodes[0];
      const nodeMap = new Map();
      const pad = 40;
      let orbitAngle = 0;
      let yaw = 0;
      let pitch = 0;
      let zoom = 1;
      let dragging = false;
      let moved = false;
      let dragStart = null;

      const resize = () => {
        const dpr = window.devicePixelRatio || 1;
        const cssWidth = Math.max(640, Math.floor(graphRoot.clientWidth - 2));
        const cssHeight = 460;
        canvas.style.width = `${cssWidth}px`;
        canvas.style.height = `${cssHeight}px`;
        canvas.width = Math.floor(cssWidth * dpr);
        canvas.height = Math.floor(cssHeight * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      };
      resize();
      window.addEventListener('resize', resize);

      const nodes = graph.nodes.map((node, idx) => {
        const isRoot = node.id === rootNode.id;
        const angle = (Math.PI * 2 * idx) / Math.max(1, graph.nodes.length);
        const radius = isRoot ? 0 : (110 + ((idx % 5) * 18));
        const item = {
          ...node,
          x: 0,
          y: 0,
          z: 0,
          baseAngle: angle,
          baseRadius: radius,
          baseHeight: isRoot ? 0 : ((idx % 9) - 4) * 16,
          isRoot,
        };
        nodeMap.set(node.id, item);
        return item;
      });

      const maxEdges = 48;
      const baseEdges = (graph.edges || [])
        .slice()
        .sort((a, b) => Number(b.weight || 1) - Number(a.weight || 1))
        .slice(0, maxEdges);

      const computeEdges = () => {
        const minWeight = Number(minWeightInput.value || 2);
        const showPairEdges = !!pairEdgeInput.checked;
        minWeightValue.textContent = String(minWeight);
        return baseEdges.filter((edge) => {
          if (Number(edge.weight || 1) < minWeight) return false;
          const rootEdge = edge.source === rootNode.id || edge.target === rootNode.id;
          return rootEdge || showPairEdges;
        });
      };

      let activeEdges = computeEdges();
      minWeightInput.addEventListener('input', () => { activeEdges = computeEdges(); });
      pairEdgeInput.addEventListener('change', () => { activeEdges = computeEdges(); });

      if (resetViewBtn) {
        resetViewBtn.addEventListener('click', () => {
          yaw = 0;
          pitch = 0;
          zoom = 1;
          orbitAngle = 0;
        });
      }

      canvas.addEventListener('pointerdown', (event) => {
        dragging = true;
        moved = false;
        dragStart = { x: event.clientX, y: event.clientY };
        canvas.setPointerCapture(event.pointerId);
      });
      canvas.addEventListener('pointerup', (event) => {
        dragging = false;
        canvas.releasePointerCapture(event.pointerId);
        dragStart = null;
      });
      canvas.addEventListener('pointerleave', () => {
        dragging = false;
        dragStart = null;
      });
      canvas.addEventListener('pointermove', (event) => {
        if (!dragging || !dragStart) return;
        const dx = event.clientX - dragStart.x;
        const dy = event.clientY - dragStart.y;
        if (Math.abs(dx) > 2 || Math.abs(dy) > 2) {
          moved = true;
        }
        dragStart = { x: event.clientX, y: event.clientY };
        yaw += dx * 0.006;
        pitch = Math.max(-1.1, Math.min(1.1, pitch + (dy * 0.0045)));
      });
      canvas.addEventListener('wheel', (event) => {
        event.preventDefault();
        const delta = event.deltaY > 0 ? -0.08 : 0.08;
        zoom = Math.max(0.55, Math.min(1.85, zoom + delta));
      }, { passive: false });

      canvas.addEventListener('click', (event) => {
        if (moved) {
          moved = false;
          return;
        }
        const rect = canvas.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;
        let hit = null;
        let best = Number.POSITIVE_INFINITY;
        nodes.forEach((node) => {
          const depthScale = Math.max(0.55, Math.min(1.6, 1 + node.z / 260));
          const dx = x - node.x;
          const dy = y - node.y;
          const dist = Math.sqrt((dx * dx) + (dy * dy));
          const radius = (node.isRoot ? 12 : 7) * depthScale;
          if (dist <= radius && dist < best) {
            best = dist;
            hit = node;
          }
        });
        if (hit?.url) {
          window.location.href = hit.url;
        }
      });

      const animate = () => {
        const cssWidth = canvas.clientWidth;
        const cssHeight = canvas.clientHeight;
        const centerX = cssWidth / 2;
        const centerY = cssHeight / 2;
        ctx.clearRect(0, 0, cssWidth, cssHeight);
        ctx.fillStyle = 'rgba(2,9,6,0.55)';
        ctx.fillRect(0, 0, cssWidth, cssHeight);

        orbitAngle += orbitInput.checked ? 0.006 : 0;
        const perspective = 420;

        nodes.forEach((node) => {
          if (node.isRoot) {
            node.x = centerX;
            node.y = centerY;
            node.z = 140;
            return;
          }
          const a = node.baseAngle + orbitAngle + yaw + (node.baseHeight * 0.001);
          const radius = node.baseRadius * zoom;
          const rx = Math.cos(a) * radius;
          const ry = Math.sin(a) * (radius * 0.62);
          const baseZ = orbitInput.checked ? (Math.sin(a * 1.6) * 120) + node.baseHeight : node.baseHeight;
          const z = (baseZ * Math.cos(pitch)) + (ry * Math.sin(pitch) * 0.6);
          const scale = perspective / (perspective - z);
          node.x = Math.max(pad, Math.min(cssWidth - pad, centerX + (rx * scale)));
          node.y = Math.max(pad, Math.min(cssHeight - pad, centerY + ((ry * Math.cos(pitch)) * scale)));
          node.z = z;
        });

        activeEdges.forEach((edge) => {
          const a = nodeMap.get(edge.source);
          const b = nodeMap.get(edge.target);
          if (!a || !b) return;
          const w = Math.max(1, Number(edge.weight || 1));
          const depth = Math.max(0.2, Math.min(0.95, 0.5 + ((a.z + b.z) / 420)));
          ctx.strokeStyle = `rgba(73,224,142,${(0.15 + (w * 0.08)) * depth})`;
          ctx.lineWidth = Math.min(4.2, 0.7 + (w * 0.55));
          ctx.beginPath();
          ctx.moveTo(a.x, a.y);
          ctx.lineTo(b.x, b.y);
          ctx.stroke();
        });

        nodes
          .slice()
          .sort((a, b) => a.z - b.z)
          .forEach((node) => {
            const depthScale = Math.max(0.55, Math.min(1.6, 1 + node.z / 260));
            const radius = (node.isRoot ? 11 : 6 + Math.min(7, Number(node.score || 1))) * depthScale;
            const alpha = Math.max(0.5, Math.min(1, 0.75 + (node.z / 300)));
            ctx.fillStyle = node.isRoot ? `rgba(103,247,174,${alpha})` : `rgba(34,211,238,${alpha})`;
            ctx.beginPath();
            ctx.arc(node.x, node.y, radius, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = 'rgba(214,255,235,0.55)';
            ctx.lineWidth = 1;
            ctx.stroke();

            const label = String(node.label || '').slice(0, node.isRoot ? 28 : 20);
            ctx.fillStyle = `rgba(217,255,233,${Math.max(0.45, alpha)})`;
            ctx.font = node.isRoot ? '700 12px JetBrains Mono' : '500 11px JetBrains Mono';
            ctx.textAlign = 'center';
            ctx.fillText(label, node.x, node.y + radius + 13);
          });

        requestAnimationFrame(animate);
      };
      animate();
    }
  }
})();
</script>
