<div class="mx-auto flex max-w-5xl flex-col gap-8 px-3 py-6 sm:px-4 lg:flex-row lg:gap-10">
    {{-- Books list --}}
    <aside class="w-full shrink-0 lg:w-56">
        <div class="mb-4 flex items-baseline justify-between gap-2">
            <h1 class="text-lg font-bold text-body">Read</h1>
            <button type="button" wire:click="startAddBook"
                    class="text-xs text-muted transition hover:text-link">+ book</button>
        </div>

        <div class="mb-4 flex gap-4 text-xs text-muted">
            <div>
                <span class="text-faint">today</span>
                <span class="ml-1 tabular-nums text-body">{{ $pageTotals['todayDuration'] }}</span>
            </div>
            <div>
                <span class="text-faint">week</span>
                <span class="ml-1 tabular-nums text-body">{{ $pageTotals['weekDuration'] }}</span>
            </div>
        </div>

        @if ($addingBook)
            <form wire:submit="addBook" class="mb-4 space-y-2">
                <input type="text" wire:model="newBookTitle" placeholder="Title" required
                       class="w-full border-b border-white/15 bg-transparent px-0 py-1.5 text-sm focus:border-link focus:outline-none" />
                @error('newBookTitle') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                <input type="text" wire:model="newBookAuthor" placeholder="Author"
                       class="w-full border-b border-white/10 bg-transparent px-0 py-1.5 text-sm text-muted focus:border-link focus:outline-none" />
                <input type="number" min="0" wire:model="newBookTotalPages" placeholder="Pages"
                       class="w-full border-b border-white/10 bg-transparent px-0 py-1.5 text-sm text-muted focus:border-link focus:outline-none" />
                @error('newBookTotalPages') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                <div class="flex gap-3 pt-1">
                    <button type="submit" class="text-xs text-link hover:underline">Add</button>
                    <button type="button" wire:click="cancelForms" class="text-xs text-muted">Cancel</button>
                </div>
            </form>
        @endif

        <ul class="space-y-0.5">
            @forelse ($books as $book)
                <li wire:key="book-{{ $book->id }}">
                    <button type="button" wire:click="selectBook({{ $book->id }})"
                            @class([
                                'w-full rounded px-2 py-1.5 text-left text-sm transition',
                                'bg-white/5 text-body' => $selected?->id === $book->id,
                                'text-muted hover:text-body' => $selected?->id !== $book->id,
                            ])>
                        <span class="block truncate font-medium">{{ $book->title }}</span>
                        <span class="mt-0.5 flex items-center gap-1.5 text-[11px] text-faint">
                            @if ($book->author)
                                <span class="truncate">{{ $book->author }}</span>
                            @endif
                            @if ($book->isFinished())
                                <span>finished</span>
                            @endif
                            @if ($book->readingSessions->isNotEmpty())
                                <span class="text-mark">●</span>
                            @endif
                        </span>
                    </button>
                </li>
            @empty
                <li class="px-2 py-6 text-sm text-muted">No books yet.</li>
            @endforelse
        </ul>
    </aside>

    {{-- Book detail — minimal --}}
    <div class="min-w-0 flex-1">
        @if (! $selected)
            <p class="py-12 text-sm text-muted">Select or add a book.</p>
        @else
            @if ($editingBookId === $selected->id)
                <form wire:submit="saveEditBook" class="mb-6 space-y-2">
                    <input type="text" wire:model="editBookTitle"
                           class="w-full border-b border-white/15 bg-transparent px-0 py-1.5 text-lg font-semibold focus:border-link focus:outline-none" />
                    <div class="flex flex-wrap gap-4">
                        <input type="text" wire:model="editBookAuthor" placeholder="Author"
                               class="border-b border-white/10 bg-transparent px-0 py-1 text-sm text-muted focus:border-link focus:outline-none sm:w-40" />
                        <input type="number" min="0" wire:model="editBookTotalPages" placeholder="Pages"
                               class="w-24 border-b border-white/10 bg-transparent px-0 py-1 text-sm text-muted focus:border-link focus:outline-none" />
                        <select wire:model="editBookStatus"
                                class="border-b border-white/10 bg-transparent px-0 py-1 text-sm text-muted focus:border-link focus:outline-none">
                            <option value="reading">reading</option>
                            <option value="finished">finished</option>
                        </select>
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button type="submit" class="text-xs text-link hover:underline">Save</button>
                        <button type="button" wire:click="cancelForms" class="text-xs text-muted">Cancel</button>
                    </div>
                </form>
            @else
                <div class="mb-1 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <h2 class="text-xl font-semibold text-body">{{ $selected->title }}</h2>
                    @if ($selected->author)
                        <span class="text-sm text-muted">{{ $selected->author }}</span>
                    @endif
                    @if ($selected->isFinished())
                        <span class="text-[11px] uppercase tracking-wide text-faint">finished</span>
                    @endif
                </div>

                <div class="mb-2 flex flex-wrap items-center gap-x-2 text-xs text-muted">
                    <span class="tabular-nums">{{ $bookTotals['totalDuration'] }}</span>
                    <span class="text-faint">·</span>
                    <span>{{ $bookTotals['sessionCount'] }} {{ $bookTotals['sessionCount'] === 1 ? 'session' : 'sessions' }}</span>
                    @if ($bookTotals['pagesRead'])
                        <span class="text-faint">·</span>
                        <span>{{ $bookTotals['pagesRead'] }} pages</span>
                    @endif
                    @if ($bookTotals['lastRead'])
                        <span class="text-faint">·</span>
                        <span>last {{ $bookTotals['lastRead']->timezone($timezone)->format('j M') }}</span>
                    @endif
                    @if ($selected->total_pages)
                        <span class="text-faint">·</span>
                        <span>{{ $selected->total_pages }} pp</span>
                    @endif
                </div>

                <div class="mb-5 flex flex-wrap items-center gap-x-3 text-[11px] text-faint">
                    @if ($runningSession)
                        <button type="button" wire:click="stopSession({{ $runningSession->id }})"
                                class="text-mark hover:underline">stop</button>
                        <span>since {{ $runningSession->started_at->timezone($timezone)->format('H:i') }}</span>
                    @else
                        <button type="button" wire:click="startSessionNow"
                                class="hover:text-link">start</button>
                    @endif
                    <button type="button" wire:click="startAddSession" class="hover:text-link">+ session</button>
                    <button type="button" wire:click="startEditBook({{ $selected->id }})" class="hover:text-body">edit</button>
                    @if ($selected->isReading())
                        <button type="button" wire:click="markBookFinished({{ $selected->id }})" class="hover:text-body">finish</button>
                    @else
                        <button type="button" wire:click="markBookReading({{ $selected->id }})" class="hover:text-body">reopen</button>
                    @endif
                    <button type="button"
                            wire:click="deleteBook({{ $selected->id }})"
                            wire:confirm="Delete this book and all its sessions?"
                            class="hover:text-red-400">delete</button>
                </div>
            @endif

            @if ($addingSession)
                <form wire:submit="addSession" class="mb-5 space-y-2 border-l border-white/10 pl-3">
                    <div class="flex flex-wrap gap-3">
                        <div>
                            <label class="mb-0.5 block text-[10px] text-faint">Start</label>
                            <input type="datetime-local" wire:model="newSessionStartedAt" required
                                   class="border-b border-white/15 bg-transparent px-0 py-1 text-sm focus:border-link focus:outline-none" />
                            @error('newSessionStartedAt') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-0.5 block text-[10px] text-faint">Finish</label>
                            <input type="datetime-local" wire:model="newSessionFinishedAt" required
                                   class="border-b border-white/15 bg-transparent px-0 py-1 text-sm focus:border-link focus:outline-none" />
                            @error('newSessionFinishedAt') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-0.5 block text-[10px] text-faint">Pages</label>
                            <input type="number" min="0" wire:model="newSessionPagesRead" placeholder="—"
                                   class="w-20 border-b border-white/10 bg-transparent px-0 py-1 text-sm focus:border-link focus:outline-none" />
                        </div>
                        <div class="min-w-[8rem] flex-1">
                            <label class="mb-0.5 block text-[10px] text-faint">Note</label>
                            <input type="text" wire:model="newSessionNote" placeholder="Optional"
                                   class="w-full border-b border-white/10 bg-transparent px-0 py-1 text-sm focus:border-link focus:outline-none" />
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="text-xs text-link hover:underline">Save</button>
                        <button type="button" wire:click="cancelForms" class="text-xs text-muted">Cancel</button>
                    </div>
                </form>
            @endif

            <ul class="divide-y divide-white/5">
                @forelse ($sessions as $session)
                    <li wire:key="session-{{ $session->id }}" class="py-2">
                        @if ($editingSessionId === $session->id)
                            <form wire:submit="saveEditSession" class="space-y-2">
                                <div class="flex flex-wrap gap-3">
                                    <input type="datetime-local" wire:model="editSessionStartedAt" required
                                           class="border-b border-white/15 bg-transparent px-0 py-1 text-sm focus:border-link focus:outline-none" />
                                    <input type="datetime-local" wire:model="editSessionFinishedAt"
                                           class="border-b border-white/15 bg-transparent px-0 py-1 text-sm focus:border-link focus:outline-none" />
                                    <input type="number" min="0" wire:model="editSessionPagesRead" placeholder="Pages"
                                           class="w-20 border-b border-white/10 bg-transparent px-0 py-1 text-sm focus:border-link focus:outline-none" />
                                    <input type="text" wire:model="editSessionNote" placeholder="Note"
                                           class="min-w-[8rem] flex-1 border-b border-white/10 bg-transparent px-0 py-1 text-sm focus:border-link focus:outline-none" />
                                </div>
                                @error('editSessionStartedAt') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                                @error('editSessionFinishedAt') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                                <div class="flex gap-3">
                                    <button type="submit" class="text-xs text-link hover:underline">Save</button>
                                    <button type="button" wire:click="cancelForms" class="text-xs text-muted">Cancel</button>
                                </div>
                            </form>
                        @else
                            <div class="group flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <div class="min-w-0 text-sm">
                                    <span class="text-body">{{ $session->started_at->timezone($timezone)->format('j M') }}</span>
                                    <span class="ml-2 tabular-nums text-muted">
                                        {{ $session->started_at->timezone($timezone)->format('H:i') }}
                                        @if ($session->isRunning())
                                            <span class="text-mark">→ …</span>
                                        @else
                                            – {{ $session->finished_at->timezone($timezone)->format('H:i') }}
                                        @endif
                                    </span>
                                    @if ($session->isRunning())
                                        <span class="ml-2 text-[10px] uppercase tracking-wide text-mark">live</span>
                                    @else
                                        <span class="ml-2 tabular-nums text-link">{{ $this->formatDuration($session->durationMinutes()) }}</span>
                                    @endif
                                    @if ($session->pages_read)
                                        <span class="ml-2 text-xs text-faint">{{ $session->pages_read }}p</span>
                                    @endif
                                    @if ($session->note)
                                        <span class="ml-2 text-xs text-faint">{{ $session->note }}</span>
                                    @endif
                                </div>
                                <div class="flex gap-2 text-[11px] text-faint opacity-60 transition group-hover:opacity-100">
                                    @if ($session->isRunning())
                                        <button type="button" wire:click="stopSession({{ $session->id }})" class="hover:text-mark">stop</button>
                                    @endif
                                    <button type="button" wire:click="startEditSession({{ $session->id }})" class="hover:text-body">edit</button>
                                    <button type="button"
                                            wire:click="deleteSession({{ $session->id }})"
                                            wire:confirm="Delete this session?"
                                            class="hover:text-red-400">×</button>
                                </div>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="py-6 text-sm text-muted">No sessions yet.</li>
                @endforelse
            </ul>
        @endif
    </div>
</div>
