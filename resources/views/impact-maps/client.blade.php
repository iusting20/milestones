@extends('impact-maps.layout')

@section('title', $client.' · Impact Maps')

@section('content')
    <div>
        <header style="display: flex; justify-content: space-between; align-items: baseline; gap: 16px;">
            <h1>Impact Maps</h1>
            @if ($loggedIn) <a class="back-link" href="{{ route('impact-maps.index') }}">&larr; back to all maps</a> @endif
        </header>

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
@endsection
