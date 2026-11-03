@extends('impact-maps.layout')

@section('title', $map->name.' · Impact Map')

@section('content')
    @php $p = ['map' => $map->slug]; @endphp
    <header style="display: flex; justify-content: space-between; align-items: baseline; gap: 16px;">
        <h1>{{ $map->name }}</h1>
        @if ($map->client) <span class="client" style="text-align: right;">{{ $map->client }}</span>@endif
    </header>
    @if ($errors->any()) <p class="err">{{ $errors->first() }}</p> @endif

    <div class="board">
        <div class="canvas" data-row="{{ \App\Support\ImpactMapLayout::ROW }}" data-top="{{ \App\Support\ImpactMapLayout::TOP }}" style="width: {{ $layout['width'] }}px; height: {{ $layout['height'] }}px;">
            <svg width="{{ $layout['width'] }}" height="{{ $layout['height'] }}" aria-hidden="true">
                @foreach ($layout['links'] as $l)
                    @php $mx = ($l['x1'] + $l['x2']) / 2; @endphp
                    <path d="M{{ $l['x1'] }},{{ $l['y1'] }} C{{ $mx }},{{ $l['y1'] }} {{ $mx }},{{ $l['y2'] }} {{ $l['x2'] }},{{ $l['y2'] }}"
                          fill="none" stroke="#2b2b2b" stroke-width="2.5" stroke-linecap="round"/>
                @endforeach
            </svg>
            @foreach (array_slice($levels, 0, 4) as $i => $label)
                <div class="col" style="left: {{ $columns[$i][0] }}px;">{{ $label }}</div>
            @endforeach

            @php
                // Subtle sub-labels in the Deliverable column: topmost epic (level 3) and topmost story (level 4).
                $subLabels = [];
                foreach ([3 => 'Epics', 4 => 'Stories'] as $lvl => $text) {
                    $top = collect($layout['boxes'])->where('level', $lvl)->sortBy('y')->first();
                    if ($top) { $subLabels[$top['node']->id] = [$lvl, $text]; }
                }
            @endphp
            @foreach ($layout['boxes'] as $box)
                @php
                    $node = $box['node'];
                    $np = $p + ['node' => $node->id];
                    $childLabel = $levels[$node->level + 1] ?? null;
                @endphp
                <div class="node lvl-{{ $box['level'] }}" data-node="{{ $node->id }}" data-parent="{{ $node->parent_id }}"
                     @if ($canEdit && $node->parent_id !== null) data-reorder="{{ route('impact-maps.nodes.reorder', $p + ['node' => $node->parent_id]) }}" @endif
                     style="left: {{ $box['x'] }}px; top: {{ $box['y'] }}px; width: {{ $box['w'] }}px;">
                    @isset($subLabels[$node->id])
                        <span class="sub-label sub-{{ $subLabels[$node->id][0] }}" aria-hidden="true">{{ $subLabels[$node->id][1] }}</span>
                    @endisset
                    @if ($canEdit)
                    <span class="t" title="Click to rename" onclick="imEdit(this)">{{ $node->title }}</span>
                    <form class="f-edit" method="POST" action="{{ route('impact-maps.nodes.update', $np) }}">
                        @csrf
                        @method('PATCH')
                        <input type="text" name="title" value="{{ $node->title }}" maxlength="255" required
                               onkeydown="if (event.key === 'Escape') this.closest('.node').classList.remove('editing')">
                    </form>
                    @if ($childLabel)
                        <form class="f-add" method="POST" action="{{ route('impact-maps.nodes.store', $np) }}">
                            @csrf
                            <input type="text" name="title" maxlength="255" required placeholder="New {{ strtolower($childLabel) }}"
                                   onkeydown="if (event.key === 'Escape') this.closest('.node').classList.remove('adding')">
                        </form>
                    @endif
                    <div class="tools">
                        @if ($childLabel)
                            <button type="button" title="Add {{ strtolower($childLabel) }}" aria-label="Add {{ strtolower($childLabel) }}" onclick="imAdd(this)">+</button>
                        @endif
                        @if ($node->parent_id !== null)
                            <form method="POST" action="{{ route('impact-maps.nodes.destroy', $np) }}"
                                  onsubmit="return confirm('Delete this {{ strtolower($levels[$node->level]) }} and everything under it?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Delete" aria-label="Delete">×</button>
                            </form>
                        @endif
                    </div>
                    @else
                    <span class="t" style="cursor: default">{{ $node->title }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    @php
        $tracker = $map->hasTracker();
        $checkins = $tracker ? $map->checkins : collect();
        $latest = $checkins->last();
        $clamp = fn ($v) => max(0, min(100, $map->progressPct($v)));
        $unit = $map->track_target_label ?: $map->track_start_label;
        $fmt = fn ($v) => \App\Models\ImpactMap::fmt($v);
        $trackErr = $errors->hasAny(['track_start', 'track_start_label', 'track_target', 'track_target_label', 'checkin_value', 'checkin_date', 'checkin_note']);
    @endphp
    @if ($tracker || $canEdit)
    <div class="track-wrap" id="track" data-key="im-track-{{ $map->slug }}" data-force="{{ $trackErr ? 1 : 0 }}">
        <button type="button" class="toon-btn track-toggle" aria-expanded="false" aria-controls="track-panel" onclick="imTrack()">
            <span aria-hidden="true">🏁</span> Track Progress
        </button>
        <section id="track-panel" class="track-panel" aria-label="Progress tracker">
            <div class="track-inner"><div class="track-card">
                <h2>Track progress</h2>
                @if ($tracker)
                    <div class="track-line" style="--fill: {{ $latest ? $clamp($latest->value) : 0 }}%">
                        <div class="track-bar"><div class="track-fill"></div></div>
                        <div class="track-end t-start">
                            <span class="dot"></span>
                            <span class="lbl"><small>Start</small><b>{{ $fmt($map->track_start) }}</b> {{ $map->track_start_label }}</span>
                        </div>
                        <div class="track-end t-goal">
                            <span class="flag" aria-hidden="true"></span>
                            <span class="lbl"><small>Goal</small><b>{{ $fmt($map->track_target) }}</b> {{ $map->track_target_label }}</span>
                        </div>
                        @foreach ($checkins as $c)
                            @php $cp = $map->progressPct($c->value); $isLatest = $c->is($latest); @endphp
                            <div class="track-pt{{ $isLatest ? ' latest' : '' }}{{ $cp >= 100 ? ' done' : '' }}" style="left: {{ $clamp($c->value) }}%"
                                 title="{{ $fmt($c->value) }} {{ $unit }} · {{ $c->date->format('j M Y') }}{{ $c->note ? ' · '.$c->note : '' }}">
                                <span class="dot"></span>
                                @if ($isLatest)
                                    <span class="badge">{{ $cp >= 100 ? '🎉 Goal reached! ('.round($cp).'%)' : round(max(0, $cp)).'% of the way' }}</span>
                                    <span class="lbl"><b>{{ $fmt($c->value) }}</b> · {{ $c->date->format('j M') }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($checkins->isNotEmpty())
                        <ul class="checkins">
                            @foreach ($checkins->reverse() as $c)
                                <li>
                                    <span class="ck-date">{{ $c->date->format('j M Y') }}</span>
                                    <b>{{ $fmt($c->value) }}</b> {{ $unit }}
                                    <span class="muted">{{ round($map->progressPct($c->value)) }}%</span>
                                    @if ($c->note) <span class="ck-note">{{ $c->note }}</span> @endif
                                    @if ($canEdit)
                                        <form method="POST" action="{{ route('impact-maps.checkins.destroy', $p + ['checkin' => $c->id]) }}" onsubmit="return confirm('Delete this check-in?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="ck-del" title="Delete check-in" aria-label="Delete check-in">×</button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @elseif (! $canEdit)
                        <p class="muted">No check-ins yet.</p>
                    @endif

                    @if ($canEdit)
                        <form method="POST" action="{{ route('impact-maps.checkins.store', $p) }}" class="track-form">
                            @csrf
                            <label>Current value <input type="number" step="any" name="checkin_value" value="{{ old('checkin_value') }}" required></label>
                            <label>Date <input type="date" name="checkin_date" value="{{ old('checkin_date', now('Europe/Bucharest')->toDateString()) }}" required></label>
                            <label class="grow">Note <input type="text" name="checkin_note" maxlength="255" value="{{ old('checkin_note') }}" placeholder="optional"></label>
                            <button type="submit" class="toon-btn">+ Check in</button>
                        </form>
                    @endif
                @else
                    <p class="muted">Set where you start and where the finish line is.</p>
                @endif

                @if ($canEdit)
                    <details class="track-settings" @if (! $tracker || $errors->hasAny(['track_start', 'track_target', 'track_start_label', 'track_target_label'])) open @endif>
                        <summary>{{ $tracker ? 'Edit start & goal' : 'Set start & goal' }}</summary>
                        <form method="POST" action="{{ route('impact-maps.tracker.update', $p) }}" class="track-form">
                            @csrf @method('PUT')
                            <label>Start <input type="number" step="any" name="track_start" value="{{ old('track_start', $fmt($map->track_start) !== '' ? str_replace(',', '', $fmt($map->track_start)) : '') }}" required placeholder="0"></label>
                            <label class="grow">Start label <input type="text" name="track_start_label" maxlength="100" value="{{ old('track_start_label', $map->track_start_label) }}" placeholder="e.g. users"></label>
                            <label>Goal <input type="number" step="any" name="track_target" value="{{ old('track_target', $fmt($map->track_target) !== '' ? str_replace(',', '', $fmt($map->track_target)) : '') }}" required placeholder="1000"></label>
                            <label class="grow">Goal label <input type="text" name="track_target_label" maxlength="100" value="{{ old('track_target_label', $map->track_target_label) }}" placeholder="e.g. users"></label>
                            <button type="submit" class="toon-btn">Save</button>
                        </form>
                        @if ($tracker)
                            <form method="POST" action="{{ route('impact-maps.tracker.destroy', $p) }}" onsubmit="return confirm('Remove the tracker and all its check-ins?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="toon-btn plain" style="color: #b52b27">Remove tracker</button>
                            </form>
                        @endif
                    </details>
                @endif
                @if ($trackErr) <p class="err">{{ $errors->first() }}</p> @endif
            </div></div>
        </section>
    </div>
    @endif

    <script>
        function imTrack(force) {
            const wrap = document.getElementById('track');
            if (!wrap) return;
            const open = force ?? !wrap.classList.contains('open');
            wrap.classList.toggle('open', open);
            wrap.querySelector('.track-toggle').setAttribute('aria-expanded', open);
            try { localStorage.setItem(wrap.dataset.key, open ? '1' : '0'); } catch (e) {}
            if (open && force === undefined) setTimeout(() => wrap.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' }), 60);
        }
        (() => {
            const wrap = document.getElementById('track');
            if (!wrap) return;
            let saved = null; try { saved = localStorage.getItem(wrap.dataset.key); } catch (e) {}
            if (wrap.dataset.force === '1' || location.hash === '#track' || saved === '1') {
                requestAnimationFrame(() => requestAnimationFrame(() => imTrack(true)));
            }
        })();
        function imEdit(el) {
            const node = el.closest('.node');
            node.classList.add('editing');
            const input = node.querySelector('.f-edit input');
            input.focus();
            input.select();
        }
        function imAdd(el) {
            const node = el.closest('.node');
            node.classList.add('adding');
            node.querySelector('.f-add input').focus();
        }

        // Drag a node (with its whole subtree) up/down among its siblings; live re-layout, no reload.
        (() => {
            const canvas = document.querySelector('.canvas');
            if (!canvas || !document.querySelector('.node[data-reorder]')) return;
            const token = @js(csrf_token());
            const ROW = +canvas.dataset.row, TOP = +canvas.dataset.top;
            const svg = canvas.querySelector('svg');
            const NS = 'http://www.w3.org/2000/svg';
            const all = [...canvas.querySelectorAll('.node[data-node]')];
            const byId = new Map(all.map((n) => [n.dataset.node, n]));
            const kids = new Map();
            let root = null;
            all.slice().sort((a, b) => parseFloat(a.style.top) - parseFloat(b.style.top)).forEach((n) => {
                const p = n.dataset.parent;
                if (!p) { root = n; return; }
                if (!kids.has(p)) kids.set(p, []);
                kids.get(p).push(n);
            });
            if (!root) return;

            // Same algorithm as App\Support\ImpactMapLayout: each leaf one row, parents centred on children.
            const layout = () => {
                const ys = new Map(); let row = 0;
                const place = (n) => {
                    const ch = kids.get(n.dataset.node) || [];
                    let y;
                    if (!ch.length) { y = TOP + row * ROW + ROW / 2; row++; }
                    else { const c = ch.map(place); y = (Math.min(...c) + Math.max(...c)) / 2; }
                    ys.set(n, y); return y;
                };
                place(root); return ys;
            };
            const subtree = (n, out = []) => { out.push(n); (kids.get(n.dataset.node) || []).forEach((c) => subtree(c, out)); return out; };

            // Connector curves, redrawn from the boxes' live (possibly animating) positions.
            svg.innerHTML = '';
            const paths = all.filter((n) => n.dataset.parent).map((n) => {
                const p = document.createElementNS(NS, 'path');
                for (const [k, v] of Object.entries({ fill: 'none', stroke: '#2b2b2b', 'stroke-width': '2.5', 'stroke-linecap': 'round' })) p.setAttribute(k, v);
                svg.appendChild(p); return [p, byId.get(n.dataset.parent), n];
            });
            const top = (n) => parseFloat(getComputedStyle(n).top);
            const draw = () => paths.forEach(([p, a, b]) => {
                const x1 = a.offsetLeft + a.offsetWidth, y1 = top(a), x2 = b.offsetLeft, y2 = top(b), mx = (x1 + x2) / 2;
                p.setAttribute('d', `M${x1},${y1} C${mx},${y1} ${mx},${y2} ${x2},${y2}`);
            });
            let animUntil = 0, raf = 0;
            const tick = () => { draw(); raf = (drag || performance.now() < animUntil) ? requestAnimationFrame(tick) : 0; };
            const animate = (ms = 260) => { animUntil = performance.now() + ms; if (!raf) raf = requestAnimationFrame(tick); };
            draw();

            const apply = (ys, skip) => ys.forEach((y, n) => { if (!skip || !skip.has(n)) n.style.top = y + 'px'; });
            const relabel = () => {
                const ys = layout();
                canvas.querySelectorAll('.sub-label').forEach((label) => {
                    const lvl = label.classList.contains('sub-3') ? 'lvl-3' : 'lvl-4';
                    const topmost = all.filter((n) => n.classList.contains(lvl)).sort((a, b) => ys.get(a) - ys.get(b))[0];
                    if (topmost && label.parentElement !== topmost) topmost.prepend(label);
                });
            };

            let drag = null, swallowClick = false;
            document.addEventListener('pointerdown', (e) => {
                const n = e.target.closest('.node[data-reorder]');
                if (!n || e.button !== 0 || n.matches('.editing, .adding') || e.target.closest('.tools, form, input, button')) return;
                const sibs = kids.get(n.dataset.parent) || [];
                if (sibs.length < 2) return;
                drag = { n, sibs, y0: e.clientY, on: false, id: e.pointerId, before: sibs.slice() };
            });
            document.addEventListener('pointermove', (e) => {
                if (!drag || e.pointerId !== drag.id) return;
                const dy = e.clientY - drag.y0;
                if (!drag.on) {
                    if (Math.abs(dy) < 6) return;
                    drag.on = true;
                    drag.tree = new Set(subtree(drag.n));
                    drag.start = new Map([...drag.tree].map((m) => [m, parseFloat(m.style.top)]));
                    // Fixed reference points: each other sibling's centre at drag start.
                    drag.ref = drag.before.filter((s) => s !== drag.n).map((s) => [s, parseFloat(s.style.top)]);
                    drag.tree.forEach((m) => m.classList.add('lifted'));
                    drag.n.classList.add('lift-root');
                    document.body.classList.add('im-dragging');
                    animate();
                }
                e.preventDefault();
                const centre = drag.start.get(drag.n) + dy;
                const others = drag.ref.filter(([, y]) => y < centre).length;
                const order = drag.ref.map(([s]) => s); order.splice(others, 0, drag.n);
                kids.set(drag.n.dataset.parent, order);
                const ys = layout();
                apply(ys, drag.tree);                                   // siblings slide (CSS transition)
                drag.tree.forEach((m) => m.style.top = (drag.start.get(m) + dy) + 'px'); // subtree follows pointer
                drag.ys = ys;
                if (!raf) raf = requestAnimationFrame(tick);
            });
            const end = (e) => {
                if (!drag || e.pointerId !== drag.id) return;
                const d = drag; drag = null;
                if (!d.on) return;
                swallowClick = true; setTimeout(() => swallowClick = false, 0);
                document.body.classList.remove('im-dragging');
                d.tree.forEach((m) => m.classList.remove('lifted'));
                d.n.classList.remove('lift-root');
                const order = kids.get(d.n.dataset.parent);
                apply(layout());                                        // settle into place
                relabel();
                animate(320);
                if (order.every((s, i) => s === d.before[i])) return;
                fetch(d.n.dataset.reorder, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ children: order.map((s) => Number(s.dataset.node)) }),
                }).then((r) => { if (!r.ok) throw r; })
                  .catch(() => { alert('Could not save the new order.'); location.reload(); });
            };
            document.addEventListener('pointerup', end);
            document.addEventListener('pointercancel', end);
            document.addEventListener('click', (e) => { if (swallowClick) { e.stopPropagation(); e.preventDefault(); } }, true);
        })();
    </script>
@endsection
