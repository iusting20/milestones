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
                <li>
                    <a href="{{ route('impact-maps.show', ['map' => $map->slug]) }}">{{ $map->name }}</a>
                    <span class="muted">{{ $map->nodes_count }} nodes · {{ $map->updated_at->timezone('Europe/Bucharest')->format('j M Y H:i') }}</span>
                </li>
            @endforeach
        </ul>
        @endforeach
    @endif
@endsection
