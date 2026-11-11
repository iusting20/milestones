<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" sizes="any">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <title>{{ $title ?? \Illuminate\Support\Facades\View::yieldContent('title', 'Milestones') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas text-body">
    <header class="sticky top-0 z-40 border-b border-white/10 bg-sidebar/95 backdrop-blur">
        <nav class="mx-auto flex max-w-6xl items-center gap-1 overflow-x-auto px-3 py-2 text-sm sm:px-4">
            <a href="{{ route('milestones') }}"
               class="shrink-0 rounded px-3 py-1.5 no-underline {{ request()->routeIs('milestones*') ? 'bg-panel text-body font-semibold' : 'text-muted hover:text-body' }}">
                Milestones
            </a>
            <a href="{{ route('week-board') }}"
               class="shrink-0 rounded px-3 py-1.5 no-underline {{ request()->routeIs('week-board') ? 'bg-panel text-body font-semibold' : 'text-muted hover:text-body' }}">
                Week Board
            </a>
            <a href="{{ route('roadmaps') }}"
               class="shrink-0 rounded px-3 py-1.5 no-underline {{ request()->routeIs('roadmaps') ? 'bg-panel text-body font-semibold' : 'text-muted hover:text-body' }}">
                Timeline
            </a>
            <a href="{{ route('comms') }}"
               class="shrink-0 rounded px-3 py-1.5 no-underline {{ request()->routeIs('comms') ? 'bg-panel text-body font-semibold' : 'text-muted hover:text-body' }}">
                Comms
            </a>
            <span class="mx-1 h-5 w-px shrink-0 bg-white/15" aria-hidden="true"></span>
            <a href="{{ route('links.hub', 'partners') }}"
               class="shrink-0 rounded px-3 py-1.5 no-underline {{ request()->routeIs('links.*') ? 'bg-panel text-body font-semibold' : 'text-muted hover:text-body' }}">
                Links
            </a>
            @php
                $showGigs = filled(config('jobs.page_token'))
                    && \Illuminate\Support\Facades\Route::has('jobs.secret')
                    && session(\App\Http\Middleware\EnsureAuthenticated::SESSION_KEY) === true;
            @endphp
            @if ($showGigs)
                <a href="{{ route('jobs.secret', config('jobs.page_token')) }}"
                   class="ml-auto shrink-0 rounded px-3 py-1.5 no-underline {{ request()->routeIs('jobs.secret') ? 'bg-panel text-body font-semibold' : 'text-muted hover:text-body' }}">
                    Gigs
                </a>
            @endif
            <a href="{{ route('read') }}"
               class="{{ $showGigs ? '' : 'ml-auto ' }}shrink-0 rounded px-3 py-1.5 no-underline {{ request()->routeIs('read') ? 'bg-panel text-body font-semibold' : 'text-muted hover:text-body' }}">
                Read
            </a>
            {{-- Log out button hidden (not needed)
            <form method="POST" action="{{ route('logout') }}" class="ml-auto shrink-0">
                @csrf
                <button type="submit" class="rounded px-3 py-1.5 text-muted hover:text-body">Log out</button>
            </form>
            --}}
        </nav>
        @if (request()->routeIs('links.*'))
            <nav class="mx-auto flex max-w-6xl gap-1 border-t border-white/5 px-3 py-2 text-sm sm:px-4">
                <a href="{{ route('links.hub', 'partners') }}"
                   class="rounded px-3 py-1.5 no-underline {{ ($hub ?? request()->route('hub')) === 'partners' ? 'bg-check/20 text-link font-semibold' : 'text-muted hover:text-body' }}">
                    Partners
                </a>
                <a href="{{ route('links.hub', 'assets') }}"
                   class="rounded px-3 py-1.5 no-underline {{ ($hub ?? request()->route('hub')) === 'assets' ? 'bg-check/20 text-link font-semibold' : 'text-muted hover:text-body' }}">
                    Assets
                </a>
            </nav>
        @endif
    </header>

    <main>
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @livewireScripts
</body>
</html>
