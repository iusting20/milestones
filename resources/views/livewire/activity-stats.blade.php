<div class="mx-auto max-w-5xl px-3 py-6 sm:px-4">
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <h1 class="text-2xl font-bold uppercase tracking-widest text-body sm:text-3xl">Stats</h1>

        <div class="inline-flex rounded border border-white/15 p-0.5 text-xs font-semibold uppercase tracking-wide" role="group" aria-label="Period">
            <button type="button" wire:click="showMonths"
                    aria-pressed="{{ $breakdown['period'] === 'month' ? 'true' : 'false' }}"
                    @class([
                        'rounded px-3 py-1 transition',
                        'bg-link/15 text-link' => $breakdown['period'] === 'month',
                        'text-muted hover:text-body' => $breakdown['period'] !== 'month',
                    ])>Month</button>
            <button type="button" wire:click="showYears"
                    aria-pressed="{{ $breakdown['period'] === 'year' ? 'true' : 'false' }}"
                    @class([
                        'rounded px-3 py-1 transition',
                        'bg-link/15 text-link' => $breakdown['period'] === 'year',
                        'text-muted hover:text-body' => $breakdown['period'] !== 'year',
                    ])>Year</button>
        </div>

        @if ($breakdown['period'] === 'month')
            <div class="flex items-center gap-1 text-sm text-muted">
                <button type="button" wire:click="previousYear" class="rounded px-2 py-1 hover:bg-panel hover:text-body" aria-label="Previous year">‹</button>
                <span class="tabular-nums text-body">{{ $breakdown['year'] }}</span>
                <button type="button" wire:click="nextYear" @disabled($this->isCurrentYear()) class="rounded px-2 py-1 hover:bg-panel hover:text-body disabled:opacity-30" aria-label="Next year">›</button>
            </div>
        @endif

        <a href="{{ route('milestones') }}"
           class="ml-auto rounded border border-white/10 px-3 py-1.5 text-sm text-muted no-underline hover:bg-panel hover:text-body"
           wire:navigate>
            Milestones
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-white/10">
        <table class="w-full min-w-[40rem] text-left text-sm">
            <thead class="border-b border-white/10 text-[10px] uppercase tracking-[0.14em] text-muted">
                <tr>
                    <th class="px-3 py-2 font-semibold">{{ $breakdown['period'] === 'month' ? 'Month' : 'Year' }}</th>
                    <th class="px-3 py-2 font-semibold" title="Books marked finished. The month is the last reading session, or the last edit if there is none.">Books</th>
                    <th class="px-3 py-2 font-semibold">Read</th>
                    <th class="px-3 py-2 font-semibold" title="Completed milestones, by the day they sit on.">Milestones</th>
                    <th class="px-3 py-2 font-semibold" title="Finished log sessions.">Logged</th>
                    <th class="px-3 py-2 font-semibold" title="Timeline items with a completed date.">Timelines</th>
                    <th class="px-3 py-2 font-semibold" title="Week board cards marked done, counted in the week they belong to.">Week board</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($breakdown['rows'] as $row)
                    <tr wire:key="stat-{{ $row['key'] }}" data-stat-row="{{ $row['key'] }}" class="border-b border-white/5">
                        <th class="px-3 py-2 font-medium text-body">{{ $row['label'] }}</th>
                        <td class="px-3 py-2 tabular-nums text-muted">{{ $row['books'] }}</td>
                        <td class="px-3 py-2 tabular-nums text-muted">{{ $row['readDuration'] }}</td>
                        <td class="px-3 py-2 tabular-nums text-muted">{{ $row['milestones'] }}</td>
                        <td class="px-3 py-2 tabular-nums text-muted">{{ $row['loggedDuration'] }}</td>
                        <td class="px-3 py-2 tabular-nums text-muted">{{ $row['timelines'] }}</td>
                        <td class="px-3 py-2 tabular-nums text-muted">{{ $row['weekBoard'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
