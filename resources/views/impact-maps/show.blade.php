@extends('impact-maps.layout')

@section('title', $map->name.' · Impact Map')

@section('content')
    @php $p = ['map' => $map->slug]; @endphp
    <h1>{{ $map->name }}@if ($map->client) <span class="client">· {{ $map->client }}</span>@endif</h1>
    @if ($canEdit)
    <div class="bar">
        <a href="{{ route('impact-maps.index') }}" class="btn">← All maps</a>
        <form method="POST" action="{{ route('impact-maps.update', $p) }}" class="bar" style="margin:0">
            @csrf
            @method('PATCH')
            <input type="text" name="client" value="{{ $map->client }}" maxlength="255" placeholder="Client / project (optional)" aria-label="Client / project">
            <button type="submit" class="btn">Save client</button>
        </form>
        <form method="POST" action="{{ route('impact-maps.destroy', $p) }}" style="margin:0"
              onsubmit="return confirm('Delete this impact map and all its nodes?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete map</button>
        </form>
        <span class="muted">Click a box to rename · hover for + (add child) and × (delete)</span>
    </div>
    @endif
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

            @foreach ($layout['boxes'] as $box)
                @php
                    $node = $box['node'];
                    $np = $p + ['node' => $node->id];
                    $childLabel = $levels[$node->level + 1] ?? null;
                @endphp
                <div class="node lvl-{{ $box['level'] }}" data-node="{{ $node->id }}"
                     style="left: {{ $box['x'] }}px; top: {{ $box['y'] }}px; width: {{ $box['w'] }}px;">
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
    </script>
@endsection
