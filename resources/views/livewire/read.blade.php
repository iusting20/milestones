<div class="mx-auto flex max-w-6xl flex-col gap-6 px-3 py-6 sm:px-4 lg:flex-row"
     x-data="readLiveTotals(@js($liveTotals))"
     x-init="start()">
    {{-- Books sidebar --}}
    <aside class="w-full shrink-0 lg:w-72">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h1 class="text-xl font-bold text-body">Read</h1>
            <button type="button" wire:click="startAddBook"
                    class="rounded border border-white/10 px-2 py-1 text-xs text-link hover:bg-panel">
                + Book
            </button>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-2 text-xs text-muted">
            <div class="rounded border border-white/10 bg-sidebar px-2 py-1.5">
                <div class="uppercase tracking-wide text-[10px] text-faint">This week</div>
                <div class="font-semibold text-body tabular-nums" x-text="formatMinutes(weekMinutes())">{{ $pageTotals['weekDuration'] }}</div>
            </div>
            <div class="rounded border border-white/10 bg-sidebar px-2 py-1.5">
                <div class="uppercase tracking-wide text-[10px] text-faint">Today</div>
                <div class="font-semibold text-body tabular-nums" x-text="formatMinutes(todayMinutes())">{{ $pageTotals['todayDuration'] }}</div>
            </div>
        </div>

        @if ($addingBook)
            <form wire:submit="addBook" class="mb-3 space-y-2 rounded border border-white/10 bg-panel p-3">
                <input type="text" wire:model="newBookTitle" placeholder="Title" required
                       class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                @error('newBookTitle') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                <input type="text" wire:model="newBookAuthor" placeholder="Author (optional)"
                       class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                <input type="number" min="0" wire:model="newBookTotalPages" placeholder="Total pages (optional)"
                       class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                @error('newBookTotalPages') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                <div class="flex gap-2">
                    <button type="submit" class="rounded bg-check px-3 py-1.5 text-sm text-white">Add</button>
                    <button type="button" wire:click="cancelForms" class="text-sm text-muted">Cancel</button>
                </div>
            </form>
        @endif

        <ul class="space-y-1">
            @forelse ($books as $book)
                <li wire:key="book-{{ $book->id }}">
                    <button type="button" wire:click="selectBook({{ $book->id }})"
                            @class([
                                'flex w-full flex-col rounded border px-3 py-2 text-left text-sm transition',
                                'border-link/40 bg-panel text-body' => $selected?->id === $book->id,
                                'border-white/10 bg-sidebar text-muted hover:text-body' => $selected?->id !== $book->id,
                            ])>
                        <span class="font-medium text-body">{{ $book->title }}</span>
                        <span class="mt-0.5 flex flex-wrap items-center gap-x-2 text-[11px]">
                            @if ($book->author)
                                <span>{{ $book->author }}</span>
                            @endif
                            <span class="uppercase tracking-wide {{ $book->isFinished() ? 'text-check' : 'text-link' }}">
                                {{ $book->status }}
                            </span>
                            @if ($book->reading_sessions_count)
                                <span>{{ $book->reading_sessions_count }} sess</span>
                            @endif
                            @if ($book->readingSessions->isNotEmpty())
                                <span class="text-mark">● live</span>
                            @endif
                        </span>
                    </button>
                </li>
            @empty
                <li class="rounded border border-dashed border-white/15 px-3 py-6 text-center text-sm text-muted">
                    No books yet. Add one to start tracking.
                </li>
            @endforelse
        </ul>
    </aside>

    {{-- Book detail --}}
    <div class="min-w-0 flex-1">
        @if (! $selected)
            <div class="rounded border border-dashed border-white/15 px-4 py-16 text-center text-muted">
                Select or add a book to log reading sessions.
            </div>
        @else
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-body">{{ $selected->title }}</h2>
                    <p class="mt-1 text-sm text-muted">
                        @if ($selected->author)
                            {{ $selected->author }}
                            <span class="text-faint">·</span>
                        @endif
                        @if ($selected->total_pages)
                            {{ $selected->total_pages }} pages
                            <span class="text-faint">·</span>
                        @endif
                        <span class="uppercase tracking-wide {{ $selected->isFinished() ? 'text-check' : 'text-link' }}">
                            {{ $selected->status }}
                        </span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($selected->isReading())
                        <button type="button" wire:click="markBookFinished({{ $selected->id }})"
                                class="rounded border border-white/10 px-2 py-1 text-xs text-muted hover:text-body">Mark finished</button>
                    @else
                        <button type="button" wire:click="markBookReading({{ $selected->id }})"
                                class="rounded border border-white/10 px-2 py-1 text-xs text-muted hover:text-body">Mark reading</button>
                    @endif
                    <button type="button" wire:click="startEditBook({{ $selected->id }})"
                            class="rounded border border-white/10 px-2 py-1 text-xs text-muted hover:text-body">Edit</button>
                    <button type="button"
                            wire:click="deleteBook({{ $selected->id }})"
                            wire:confirm="Delete this book and all its sessions?"
                            class="rounded border border-red-500/30 px-2 py-1 text-xs text-red-400 hover:bg-red-500/10">Delete</button>
                </div>
            </div>

            @if ($editingBookId === $selected->id)
                <form wire:submit="saveEditBook" class="mb-4 flex flex-col gap-2 rounded border border-white/10 bg-panel p-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <div class="sm:w-48">
                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Title</label>
                        <input type="text" wire:model="editBookTitle"
                               class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                    </div>
                    <div class="sm:w-40">
                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Author</label>
                        <input type="text" wire:model="editBookAuthor"
                               class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                    </div>
                    <div class="sm:w-28">
                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Pages</label>
                        <input type="number" min="0" wire:model="editBookTotalPages"
                               class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                    </div>
                    <div class="sm:w-32">
                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Status</label>
                        <select wire:model="editBookStatus"
                                class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none">
                            <option value="reading">reading</option>
                            <option value="finished">finished</option>
                        </select>
                    </div>
                    <button type="submit" class="rounded bg-check px-3 py-1.5 text-sm text-white">Save</button>
                    <button type="button" wire:click="cancelForms" class="text-sm text-muted">Cancel</button>
                </form>
            @endif

            <div class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded border border-white/10 bg-sidebar px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wide text-muted">Sessions</div>
                    <div class="text-lg font-semibold tabular-nums">{{ $bookTotals['sessionCount'] }}</div>
                </div>
                <div class="rounded border border-white/10 bg-sidebar px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wide text-muted">Time read</div>
                    <div class="text-lg font-semibold tabular-nums" x-text="formatMinutes(bookMinutes())">{{ $bookTotals['totalDuration'] }}</div>
                </div>
                <div class="rounded border border-white/10 bg-sidebar px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wide text-muted">Pages logged</div>
                    <div class="text-lg font-semibold tabular-nums">{{ $bookTotals['pagesRead'] }}</div>
                </div>
                <div class="rounded border border-white/10 bg-sidebar px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wide text-muted">Last read</div>
                    <div class="text-sm font-semibold">
                        @if ($bookTotals['lastRead'])
                            {{ $bookTotals['lastRead']->timezone($timezone)->format('j M Y, H:i') }}
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap items-center gap-2">
                @if ($runningSession)
                    <button type="button" wire:click="stopSession({{ $runningSession->id }})"
                            class="rounded bg-mark px-3 py-1.5 text-sm font-medium text-black">
                        Stop session
                    </button>
                    <span class="text-xs text-muted">
                        Started {{ $runningSession->started_at->timezone($timezone)->format('j M H:i') }}
                        ·
                        <span class="font-semibold tabular-nums text-mark"
                              x-data="elapsedClock(@js($runningSession->started_at->utc()->toIso8601String()))"
                              x-init="start()"
                              x-text="display">00:00:00</span>
                    </span>
                @else
                    <button type="button" wire:click="startSessionNow"
                            class="rounded bg-check px-3 py-1.5 text-sm font-medium text-white">
                        Start session now
                    </button>
                @endif
                <button type="button" wire:click="startAddSession"
                        class="rounded border border-white/10 px-3 py-1.5 text-sm text-link hover:bg-panel">
                    + Log session
                </button>
            </div>

            @if ($addingSession)
                <form wire:submit="addSession" class="mb-4 space-y-2 rounded border border-white/10 bg-panel p-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <div>
                            <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Start</label>
                            <input type="datetime-local" wire:model="newSessionStartedAt" required
                                   class="rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                            @error('newSessionStartedAt') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Finish</label>
                            <input type="datetime-local" wire:model="newSessionFinishedAt" required
                                   class="rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                            @error('newSessionFinishedAt') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:w-28">
                            <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Pages</label>
                            <input type="number" min="0" wire:model="newSessionPagesRead" placeholder="—"
                                   class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                            @error('newSessionPagesRead') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:flex-1">
                            <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Note</label>
                            <input type="text" wire:model="newSessionNote" placeholder="Optional"
                                   class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded bg-check px-3 py-1.5 text-sm text-white">Save session</button>
                        <button type="button" wire:click="cancelForms" class="text-sm text-muted">Cancel</button>
                    </div>
                </form>
            @endif

            <h3 class="mb-2 text-sm font-semibold uppercase tracking-[0.16em] text-muted">Sessions</h3>
            <ul class="space-y-2">
                @forelse ($sessions as $session)
                    <li wire:key="session-{{ $session->id }}"
                        class="rounded border border-white/10 bg-sidebar px-3 py-2">
                        @if ($editingSessionId === $session->id)
                            <form wire:submit="saveEditSession" class="space-y-2">
                                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                    <div>
                                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Start</label>
                                        <input type="datetime-local" wire:model="editSessionStartedAt" required
                                               class="rounded border border-white/10 bg-panel px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                                        @error('editSessionStartedAt') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Finish (empty = running)</label>
                                        <input type="datetime-local" wire:model="editSessionFinishedAt"
                                               class="rounded border border-white/10 bg-panel px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                                        @error('editSessionFinishedAt') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="sm:w-28">
                                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Pages</label>
                                        <input type="number" min="0" wire:model="editSessionPagesRead"
                                               class="w-full rounded border border-white/10 bg-panel px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                                    </div>
                                    <div class="sm:flex-1">
                                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Note</label>
                                        <input type="text" wire:model="editSessionNote"
                                               class="w-full rounded border border-white/10 bg-panel px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="rounded bg-check px-3 py-1.5 text-sm text-white">Save</button>
                                    <button type="button" wire:click="cancelForms" class="text-sm text-muted">Cancel</button>
                                </div>
                            </form>
                        @else
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="text-sm text-body">
                                        {{ $session->started_at->timezone($timezone)->format('D j M Y, H:i') }}
                                        @if ($session->isRunning())
                                            <span class="ml-1 rounded bg-mark/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-mark">running</span>
                                            <span class="ml-2 font-semibold tabular-nums text-mark"
                                                  x-data="elapsedClock(@js($session->started_at->utc()->toIso8601String()))"
                                                  x-init="start()"
                                                  x-text="display">00:00:00</span>
                                        @else
                                            <span class="text-muted">→ {{ $session->finished_at->timezone($timezone)->format('H:i') }}</span>
                                            <span class="ml-2 font-semibold tabular-nums text-link">{{ $this->formatDuration($session->durationMinutes()) }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-muted">
                                        @if ($session->pages_read)
                                            <span>{{ $session->pages_read }} pages</span>
                                        @endif
                                        @if ($session->note)
                                            <span>{{ $session->note }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    @if ($session->isRunning())
                                        <button type="button" wire:click="stopSession({{ $session->id }})"
                                                class="text-xs text-mark hover:underline">stop</button>
                                    @endif
                                    <button type="button" wire:click="startEditSession({{ $session->id }})"
                                            class="text-xs text-muted hover:text-body">edit</button>
                                    <button type="button"
                                            wire:click="deleteSession({{ $session->id }})"
                                            wire:confirm="Delete this session?"
                                            class="text-xs text-red-400/80 hover:text-red-400">delete</button>
                                </div>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="rounded border border-dashed border-white/15 px-3 py-8 text-center text-sm text-muted">
                        No sessions yet. Start one now or log a past session.
                    </li>
                @endforelse
            </ul>
        @endif
    </div>

    <script>
        function formatReadMinutes(minutes) {
            minutes = Math.max(0, Math.floor(minutes));
            if (minutes <= 0) return '0m';
            const hours = Math.floor(minutes / 60);
            const remaining = minutes % 60;
            if (hours === 0) return remaining + 'm';
            if (remaining === 0) return hours + 'h';
            return hours + 'h ' + remaining + 'm';
        }

        function readLiveTotals(cfg) {
            return {
                todayBase: cfg.todayBase || 0,
                weekBase: cfg.weekBase || 0,
                bookBase: cfg.bookBase || 0,
                todayRuns: cfg.todayRuns || [],
                weekRuns: cfg.weekRuns || [],
                bookRuns: cfg.bookRuns || [],
                tick: 0,
                start() {
                    setInterval(() => { this.tick = Date.now(); }, 1000);
                },
                sumRuns(runs) {
                    void this.tick;
                    const now = Date.now();
                    return runs.reduce((sum, startMs) => sum + Math.max(0, Math.floor((now - startMs) / 60000)), 0);
                },
                todayMinutes() { return this.todayBase + this.sumRuns(this.todayRuns); },
                weekMinutes() { return this.weekBase + this.sumRuns(this.weekRuns); },
                bookMinutes() { return this.bookBase + this.sumRuns(this.bookRuns); },
                formatMinutes(m) { return formatReadMinutes(m); },
            };
        }

        function elapsedClock(isoStart) {
            return {
                display: '00:00:00',
                start() {
                    const startMs = new Date(isoStart).getTime();
                    const tick = () => {
                        const total = Math.max(0, Math.floor((Date.now() - startMs) / 1000));
                        const h = String(Math.floor(total / 3600)).padStart(2, '0');
                        const m = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
                        const s = String(total % 60).padStart(2, '0');
                        this.display = `${h}:${m}:${s}`;
                    };
                    tick();
                    setInterval(tick, 1000);
                },
            };
        }
    </script>
</div>
