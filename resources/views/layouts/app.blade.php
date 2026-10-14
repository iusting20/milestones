<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" sizes="any">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <title>@yield('title', config('app.name', 'Milestones'))</title>
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
            <a href="{{ route('backlog') }}"
               class="shrink-0 rounded px-3 py-1.5 no-underline {{ request()->routeIs('backlog*') ? 'bg-panel text-body font-semibold' : 'text-muted hover:text-body' }}">
                Backlog
            </a>
            <a href="{{ route('roadmaps') }}"
               class="shrink-0 rounded px-3 py-1.5 no-underline {{ request()->routeIs('roadmaps') ? 'bg-panel text-body font-semibold' : 'text-muted hover:text-body' }}">
                Roadmaps
            </a>
            <span class="mx-1 h-5 w-px shrink-0 bg-white/15" aria-hidden="true"></span>
            <a href="{{ route('links.hub', 'partners') }}"
               class="shrink-0 rounded px-3 py-1.5 no-underline {{ request()->routeIs('links.*') ? 'bg-panel text-body font-semibold' : 'text-muted hover:text-body' }}">
                Links
            </a>
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
