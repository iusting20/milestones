@extends('impact-maps.layout')

@section('content')
    <h1>Impact Maps</h1>

    <form method="POST" action="{{ route('impact-maps.store') }}" class="bar">
        @csrf
        <input type="text" name="name" placeholder="Goal / map name" maxlength="255" required value="{{ old('name') }}">
        <input type="text" name="client" placeholder="Client / project (optional)" maxlength="255" value="{{ old('client') }}">
        <button type="submit" class="btn">+ New map</button>
        @error('name') <span class="err">{{ $message }}</span> @enderror
    </form>

    @if ($maps->isEmpty())
        <p class="muted">No impact maps yet. Create one above.</p>
    @else
        @foreach ($maps as $client => $group)
        <h2 class="group">{{ $client !== '' ? $client : 'No client / project' }}</h2>
        <ul class="maps">
            @foreach ($group as $map)
                <li x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                    <button type="button" class="cog" title="Settings" aria-label="Map settings" @click="open = !open">⚙</button>
                    <a href="{{ route('impact-maps.show', ['map' => $map->slug]) }}">{{ $map->name }}</a>
                    <span class="muted">{{ $map->nodes_count }} nodes · {{ $map->updated_at->timezone('Europe/Bucharest')->format('j M Y H:i') }}</span>
                    <div class="cog-menu" x-show="open" x-cloak>
                        <form method="POST" action="{{ route('impact-maps.update', ['map' => $map->slug]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="text" name="client" value="{{ $map->client }}" maxlength="255" placeholder="Client / project (optional)" aria-label="Client / project">
                            <button type="submit" class="btn">Save</button>
                        </form>
                        <form method="POST" action="{{ route('impact-maps.destroy', ['map' => $map->slug]) }}"
                              onsubmit="return confirm('Delete this impact map and all its nodes?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Delete map</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
        @endforeach
    @endif
@push('scripts')
    @livewireScripts
@endpush
@endsection
