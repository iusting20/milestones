<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Log in</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-body">
    <main class="mx-auto flex min-h-screen max-w-sm items-center px-4">
        <form method="POST" action="{{ route('login.store') }}" class="w-full rounded-lg border border-white/10 bg-panel p-6">
            @csrf
            <h1 class="mb-4 text-lg font-semibold">Log in</h1>

            <label class="mb-3 block text-sm">
                <span class="mb-1 block text-muted">User</span>
                <input type="text" name="user" value="{{ old('user') }}" required autofocus autocomplete="username"
                       class="w-full rounded border border-white/15 bg-canvas px-3 py-2 text-body">
            </label>

            <label class="mb-4 block text-sm">
                <span class="mb-1 block text-muted">Password</span>
                <input type="password" name="password" required autocomplete="current-password"
                       class="w-full rounded border border-white/15 bg-canvas px-3 py-2 text-body">
            </label>

            <label class="mb-4 flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="remember" value="1" @checked(old('remember', '1') === '1') class="rounded border-white/20 bg-canvas">
                Remember me
            </label>

            @error('user')
                <p class="mb-4 text-sm text-red-400">{{ $message }}</p>
            @enderror

            <button type="submit" class="w-full rounded bg-check/30 px-3 py-2 font-semibold text-link">
                Log in
            </button>
        </form>
    </main>
</body>
</html>
