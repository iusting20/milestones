@extends('impact-maps.layout')

@section('title', $client.' · Impact Maps')

@section('content')
    @if ($loggedIn)
    <div x-data="{ create: {{ $errors->has('name') ? 'true' : 'false' }}, tpl: false,
            pickTpl(t) { this.$refs.mapName.value = t; this.tpl = false; this.$nextTick(() => { this.$refs.mapName.focus(); this.$refs.mapName.select(); }); },
            closeTpl() { this.tpl = false; this.$nextTick(() => this.$refs.tplBtn.focus()); } }"
         x-init="$watch('create', v => v && $nextTick(() => $refs.mapName.focus()))"
         @keydown.escape.window="tpl ? closeTpl() : (create = false)">
    @else
    <div>
    @endif
        <h1>Impact Maps</h1>

        @if ($loggedIn)
        <div class="bar">
            <button type="button" class="toon-btn" @click="create = true">+ New map</button>
        </div>

        @php
            $templates = [
                ['📣', 'Increase awareness', '#7b86c6'],
                ['🧲', 'Increase acquisition rate', '#9fbf3b'],
                ['⚡', 'Increase activation rate', '#f9b11b'],
                ['💬', 'Increase engagement rate', '#ef7a7a'],
                ['🔁', 'Increase retention rate', '#7b86c6'],
                ['💰', 'Increase revenue', '#9fbf3b'],
                ['🤝', 'Increase referrals', '#fbd75b'],
            ];
        @endphp
        <div class="modal-bg tpl-bg" x-show="tpl" x-cloak x-transition.opacity @click.self="closeTpl()">
            <div class="modal tpl-modal" role="dialog" aria-modal="true" aria-labelledby="tpl-title">
                <h2 id="tpl-title">Goal templates</h2>
                <div class="tpl-grid">
                    @foreach ($templates as [$icon, $label, $color])
                        <button type="button" class="tpl-card" style="--c: {{ $color }}" @click="pickTpl(@js($label))">
                            <span class="tpl-ico" aria-hidden="true">{{ $icon }}</span>{{ $label }}
                        </button>
                    @endforeach
                </div>
                <div class="actions"><button type="button" class="toon-btn plain" @click="closeTpl()">Back</button></div>
            </div>
        </div>
        <div class="modal-bg" x-show="create" x-cloak x-transition.opacity @click.self="create = false">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="new-map-title">
                <h2 id="new-map-title">New impact map</h2>
                <form method="POST" action="{{ route('impact-maps.client.store', ['secret' => $secret]) }}">
                    @csrf
                    <div class="name-row">
                        <label for="map-name">Goal / map name</label>
                        <button type="button" class="tpl-btn" x-ref="tplBtn" @click="tpl = true" aria-haspopup="dialog">view templates</button>
                    </div>
                    <input id="map-name" x-ref="mapName" type="text" name="name" maxlength="255" required value="{{ old('name') }}">
                    @error('name') <p class="err" style="margin: -8px 0 10px">{{ $message }}</p> @enderror
                    <div class="actions">
                        <button type="button" class="toon-btn plain" @click="create = false">Cancel</button>
                        <button type="submit" class="toon-btn">Create</button>
                    </div>
                </form>
            </div>
        </div>

        @endif

        <section class="grp">
            <h2 class="group">{{ $client }}</h2>
            @if ($maps->isEmpty())
                <p class="muted">No impact maps yet.</p>
            @else
                <ul class="maps">
                    @foreach ($maps as $map)
                        <li>
                            <a href="{{ route('impact-maps.show', ['map' => $map->slug]) }}">{{ $map->name }}</a>
                            <span class="meta"><span class="muted">{{ $map->updated_at->timezone('Europe/Bucharest')->format('j M Y') }}</span></span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@if ($loggedIn)
@push('scripts')
    @livewireScripts
@endpush
@endif
@endsection
