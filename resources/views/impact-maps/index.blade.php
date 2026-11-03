@extends('impact-maps.layout')

@section('content')
    <div x-data="{ create: {{ $errors->has('name') || $errors->has('client') ? 'true' : 'false' }} }"
         x-init="$watch('create', v => v && $nextTick(() => $refs.mapName.focus()))"
         @keydown.escape.window="create = false">
        <h1>Impact Maps</h1>

        <div class="bar">
            <button type="button" class="toon-btn" @click="create = true">+ New map</button>
        </div>

        <div class="modal-bg" x-show="create" x-cloak x-transition.opacity @click.self="create = false">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="new-map-title">
                <h2 id="new-map-title">New impact map</h2>
                <form method="POST" action="{{ route('impact-maps.store') }}">
                    @csrf
                    <label for="map-name">Goal / map name</label>
                    <input id="map-name" x-ref="mapName" type="text" name="name" maxlength="255" required value="{{ old('name') }}">
                    @error('name') <p class="err" style="margin: -8px 0 10px">{{ $message }}</p> @enderror
                    <label for="map-client">Client / project <span class="muted">(optional)</span></label>
                    <input id="map-client" type="text" name="client" maxlength="255" value="{{ old('client') }}">
                    @error('client') <p class="err" style="margin: -8px 0 10px">{{ $message }}</p> @enderror
                    <div class="actions">
                        <button type="button" class="toon-btn plain" @click="create = false">Cancel</button>
                        <button type="submit" class="toon-btn">Create</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($maps->isEmpty())
            <p class="muted">No impact maps yet. Click + New map to create one.</p>
        @else
            @foreach ($maps as $client => $group)
            <h2 class="group">{{ $client !== '' ? $client : 'No client / project' }}</h2>
            <ul class="maps">
                @foreach ($group as $map)
                    <li x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                        <a href="{{ route('impact-maps.show', ['map' => $map->slug]) }}">{{ $map->name }}</a>
                        <span class="meta">
                            <span class="muted">{{ $map->updated_at->timezone('Europe/Bucharest')->format('j M Y') }}</span>
                            <button type="button" class="wrench" title="Settings" aria-label="Map settings" :aria-expanded="open" @click="open = !open">
                                <svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true">
                                    <path d="M21.5 3.5c-3.6-.6-7 2.2-7 5.9 0 .8.1 1.5.4 2.2L4.6 21.9c-1.4 1.4-1.4 3.6 0 5 1.4 1.4 3.6 1.4 5 0l10.3-10.3c.7.3 1.4.4 2.2.4 3.7 0 6.5-3.4 5.9-7l-3.6 3.5-3.9-.9-.9-3.9z"
                                          fill="#f9b11b" stroke="#2b2b2b" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"/>
                                    <circle cx="7.4" cy="24.4" r="1.4" fill="#2b2b2b"/>
                                    <path d="M17.3 8.6c.3-1.5 1.5-2.6 3-2.9" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </span>
                        <div class="cog-menu" x-show="open" x-cloak x-transition>
                            <form method="POST" action="{{ route('impact-maps.update', ['map' => $map->slug]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="client" value="{{ $map->client }}" maxlength="255" placeholder="Client / project (optional)" aria-label="Client / project">
                                <button type="submit" class="toon-btn">Save</button>
                            </form>
                            <form method="POST" action="{{ route('impact-maps.destroy', ['map' => $map->slug]) }}"
                                  onsubmit="return confirm('Delete this impact map and all its nodes?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="toon-btn plain" style="color: #b52b27">Delete map</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
            @endforeach
        @endif
    </div>
@push('scripts')
    @livewireScripts
@endpush
@endsection
