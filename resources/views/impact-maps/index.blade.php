@extends('impact-maps.layout')

@section('content')
    <h1>Impact Maps</h1>

    <form method="POST" action="{{ route('impact-maps.store', ['token' => $token]) }}" class="bar">
        @csrf
        <input type="text" name="name" placeholder="Goal / map name" maxlength="255" required value="{{ old('name') }}">
        <button type="submit" class="btn">+ New map</button>
        @error('name') <span class="err">{{ $message }}</span> @enderror
    </form>

    @if ($maps->isEmpty())
        <p class="muted">No impact maps yet. Create one above.</p>
    @else
        <ul class="maps">
            @foreach ($maps as $map)
                <li>
                    <a href="{{ route('impact-maps.show', ['token' => $token, 'map' => $map->id]) }}">{{ $map->name }}</a>
                    <span class="muted">{{ $map->nodes_count }} nodes · {{ $map->updated_at->timezone('Europe/Bucharest')->format('j M Y H:i') }}</span>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
