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
        <div class="canvas" style="width: {{ $layout['width'] }}px; height: {{ $layout['height'] }}px;">
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

    <script>
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

        // Drag a node up/down to reorder it among its siblings (same parent only).
        (() => {
            const token = @js(csrf_token());
            let drag = null, swallowClick = false;
            const siblings = (n) => [...document.querySelectorAll('.node[data-reorder]')].filter((s) => s.dataset.parent === n.dataset.parent);
            const mid = (n) => { const r = n.getBoundingClientRect(); return r.top + r.height / 2; };

            document.addEventListener('pointerdown', (e) => {
                const n = e.target.closest('.node[data-reorder]');
                if (!n || e.button !== 0 || n.matches('.editing, .adding') || e.target.closest('.tools, form, input, button')) return;
                if (siblings(n).length < 2) return;
                drag = { n, y0: e.clientY, dy: 0, on: false, id: e.pointerId };
            });
            document.addEventListener('pointermove', (e) => {
                if (!drag || e.pointerId !== drag.id) return;
                drag.dy = e.clientY - drag.y0;
                if (!drag.on && Math.abs(drag.dy) < 6) return;
                if (!drag.on) { drag.on = true; drag.n.classList.add('dragging'); document.body.classList.add('im-dragging'); }
                drag.n.style.transform = `translateY(calc(-50% + ${drag.dy}px))`;
                e.preventDefault();
            });
            document.addEventListener('pointerup', (e) => {
                if (!drag || e.pointerId !== drag.id) return;
                const { n, on } = drag; drag = null;
                if (!on) return;
                swallowClick = true; setTimeout(() => swallowClick = false, 0);
                document.body.classList.remove('im-dragging');
                const sibs = siblings(n);
                const before = sibs.map((s) => s.dataset.node);
                const order = sibs.map((s) => [mid(s), s.dataset.node]).sort((a, b) => a[0] - b[0]).map((x) => x[1]);
                if (order.join() === before.join()) { n.style.transform = ''; n.classList.remove('dragging'); return; }
                fetch(n.dataset.reorder, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ children: order.map(Number) }),
                }).then((r) => { if (!r.ok) throw r; location.reload(); })
                  .catch(() => { alert('Could not save the new order.'); location.reload(); });
            });
            document.addEventListener('click', (e) => { if (swallowClick) { e.stopPropagation(); e.preventDefault(); } }, true);
        })();
    </script>
@endsection
