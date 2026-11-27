<div class="mx-auto flex max-w-6xl flex-col gap-6 px-3 py-6 sm:px-4 lg:flex-row"
     x-data="readLiveTotals(@js($liveTotals))"
     x-init="start()"
     x-on:read-live-totals.window="applyTotals($event.detail.totals)">
    {{-- Books sidebar --}}
    <aside class="w-full shrink-0 lg:w-72">
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

        <div class="mb-3 flex items-center justify-between gap-2">
            <h1 class="text-xl font-bold text-body">{{ $isArticles ? 'Articles' : 'Books' }}</h1>
            <a href="{{ route('read.stats') }}" class="ml-auto text-sm font-semibold text-mark no-underline hover:opacity-80">Stats</a>
            <button type="button" wire:click="startAddBook"
                    class="rounded border border-white/10 px-2 py-1 text-xs text-link hover:bg-panel">
                + {{ $isArticles ? 'Article' : 'Book' }}
            </button>
        </div>

        @if ($addingBook)
            <form wire:submit="addBook" class="mb-3 space-y-2 rounded border border-white/10 bg-panel p-3">
                <input type="text" wire:model="newBookTitle" placeholder="{{ $isArticles ? 'Name' : 'Title' }}" required
                       class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                @error('newBookTitle') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                <input type="text" wire:model="newBookAuthor" placeholder="Author (optional)"
                       class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                @unless ($isArticles)
                <input type="number" min="0" wire:model="newBookTotalPages" placeholder="Total pages (optional)"
                       class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                @error('newBookTotalPages') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                @endunless
                <div class="flex gap-2">
                    <button type="submit" class="rounded bg-check px-3 py-1.5 text-sm text-white">Add</button>
                    <button type="button" wire:click="cancelForms" class="text-sm text-muted">Cancel</button>
                </div>
            </form>
        @endif

        <ul class="space-y-1" x-data="bookSortList()">
            @forelse ($books as $book)
                <li wire:key="book-{{ $book->id }}" data-sort-item="{{ $book->id }}" class="relative">
                    <span data-drag-handle aria-label="Drag to reorder"
                          class="absolute inset-y-0 right-0 z-10 flex w-10 items-center justify-center text-faint hover:text-muted"
                          style="cursor:grab; touch-action:none; -webkit-user-select:none; user-select:none;">
                        @include('livewire.partials.drag-handle')
                    </span>
                    <button type="button" wire:click="selectBook({{ $book->id }})"
                            @class([
                                'flex w-full items-center gap-3 rounded border py-2 pl-3 pr-10 text-left text-sm transition',
                                'border-link/40 bg-panel text-body' => $selected?->id === $book->id,
                                'border-white/10 bg-sidebar text-muted hover:text-body' => $selected?->id !== $book->id,
                            ])>
                        <span class="flex min-w-0 flex-1 flex-col">
                        <span class="text-base font-semibold leading-snug text-body">@include('livewire.partials.read-item-name', ['title' => $book->title, 'startedToday' => $book->showsStartedGlyphToday(), 'finished' => false])</span>
                        <span class="mt-0.5 flex min-h-[1rem] flex-wrap items-center gap-x-2 text-[11px]">
                            @if ($book->author)
                                <span>{{ $book->author }}</span>
                            @endif
                            @if ($book->readingSessions->isNotEmpty())
                                <span class="text-mark">● live</span>
                            @endif
                        </span>
                        </span>
                        @if ($book->type)
                            <span class="shrink-0 text-lg font-semibold text-link">{{ $book->type }}</span>
                        @endif
                    </button>
                </li>
            @empty
                <li class="rounded border border-dashed border-white/15 px-3 py-6 text-center text-sm text-muted">
                    @if ($finishedBooks->isNotEmpty())
                        No open {{ $isArticles ? 'articles' : 'books' }}.
                    @else
                        No {{ $isArticles ? 'articles' : 'books' }} yet. Add one to start tracking.
                    @endif
                </li>
            @endforelse
        </ul>
        <script>
            function bookSortList() {
                return {
                    init() {
                        const boot = () => {
                            if (this.$el._bookSortable) {
                                return;
                            }
                            if (! window.Sortable) {
                                setTimeout(boot, 100);
                                return;
                            }
                            this.$el._bookSortable = window.Sortable.create(this.$el, {
                                draggable: '[data-sort-item]',
                                handle: '[data-drag-handle]',
                                animation: 150,
                                ghostClass: 'opacity-40',
                                forceFallback: true,
                                fallbackOnBody: true,
                                fallbackTolerance: 3,
                                onEnd: () => {
                                    const ids = [...this.$el.querySelectorAll(':scope > [data-sort-item]')]
                                        .map((el) => Number(el.dataset.sortItem));
                                    this.$wire.reorderBooks(ids);
                                },
                            });
                        };
                        boot();
                    },
                };
            }
        </script>

        @if ($finishedBooks->isNotEmpty())
            <div class="mt-3">
                <button type="button" wire:click="$toggle('showFinished')"
                        class="flex items-center gap-1 text-xs text-muted no-underline hover:text-body"
                        aria-expanded="{{ $showFinished ? 'true' : 'false' }}">
                    <span class="inline-block w-3 text-[10px]">{{ $showFinished ? '▾' : '▸' }}</span>
                    Finished ({{ $finishedBooks->count() }})
                </button>
                @if ($showFinished)
                    <ul class="mt-1 space-y-1" data-finished-list>
                        @foreach ($finishedBooks as $book)
                            <li wire:key="finished-book-{{ $book->id }}" data-finished-item="{{ $book->id }}">
                                <button type="button" wire:click="selectBook({{ $book->id }})"
                                        @class([
                                            'flex w-full items-center gap-3 rounded border py-2 px-3 text-left text-sm transition',
                                            'border-link/40 bg-panel text-body' => $selected?->id === $book->id,
                                            'border-white/10 bg-sidebar text-muted hover:text-body' => $selected?->id !== $book->id,
                                        ])>
                                    <span class="flex min-w-0 flex-1 flex-col">
                                    <span class="text-base font-semibold leading-snug text-body">@include('livewire.partials.read-item-name', ['title' => $book->title, 'startedToday' => $book->showsStartedGlyphToday(), 'finished' => true])</span>
                                    <span class="mt-0.5 flex min-h-[1rem] flex-wrap items-center gap-x-2 text-[11px]">
                                        @if ($book->author)
                                            <span>{{ $book->author }}</span>
                                        @endif
                                        @if ($book->readingSessions->isNotEmpty())
                                            <span class="text-mark">● live</span>
                                        @endif
                                    </span>
                                    </span>
                                    @if ($book->type)
                                        <span class="shrink-0 text-lg font-semibold text-link">{{ $book->type }}</span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
        @if ($isArticles)
            <a href="{{ route('read') }}" class="mt-6 inline-block text-xs text-faint no-underline hover:text-muted">books</a>
        @else
            <a href="{{ route('read.articles') }}" class="mt-6 inline-block text-xs text-faint no-underline hover:text-muted">articles</a>
        @endif
    </aside>

    {{-- Book detail --}}
    <div class="min-w-0 flex-1">
        @if (! $selected)
            {{-- Nothing shown until a book is selected. --}}
        @else
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-body">@include('livewire.partials.read-item-name', ['title' => $selected->title, 'startedToday' => $selected->showsStartedGlyphToday(), 'finished' => $selected->isFinished()])</h2>
                    <p class="mt-1 text-sm text-muted">
                        @php
                            $metaParts = array_values(array_filter([
                                $selected->author ? e($selected->author) : null,
                                $selected->total_pages ? e($selected->total_pages).' pages' : null,
                                $selected->isFinished() ? '<span class="uppercase tracking-wide text-check">'.e($selected->status).'</span>' : null,
                            ]));
                        @endphp
                        {!! implode(' <span class="text-faint">·</span> ', $metaParts) !!}
                    </p>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    @if ($editMode)
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
                                wire:confirm="Delete this {{ $isArticles ? 'article' : 'book' }} and all its sessions?"
                                class="rounded border border-red-500/30 px-2 py-1 text-xs text-red-400 hover:bg-red-500/10">Delete</button>
                    @endif
                    <button type="button"
                            wire:click="openBookStats({{ $selected->id }})"
                            class="px-1 text-xs text-muted hover:text-body"
                            data-open-book-stats="{{ $selected->id }}">stats</button>
                    <button type="button"
                            wire:click="$toggle('editMode')"
                            @class([
                                'flex h-8 w-8 items-center justify-center rounded text-muted transition hover:bg-white/5 hover:text-body',
                                'bg-white/10 text-link' => $editMode,
                            ])
                            aria-label="{{ $editMode ? 'Hide edit controls' : 'Show edit controls' }}"
                            aria-pressed="{{ $editMode ? 'true' : 'false' }}">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>
                </div>
            </div>

            @if ($editingBookId === $selected->id)
                <form wire:submit="saveEditBook" class="mb-4 flex flex-col gap-2 rounded border border-white/10 bg-panel p-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <div class="sm:w-48">
                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">{{ $isArticles ? 'Name' : 'Title' }}</label>
                        <input type="text" wire:model="editBookTitle"
                               class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                    </div>
                    <div class="sm:w-40">
                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Author</label>
                        <input type="text" wire:model="editBookAuthor"
                               class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                    </div>
                    @unless ($isArticles)
                    <div class="sm:w-28">
                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Pages</label>
                        <input type="number" min="0" wire:model="editBookTotalPages"
                               class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                    </div>
                    @endunless
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

            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-stretch">
                @unless ($isArticles)
                <div class="sm:w-28 sm:shrink-0" x-data="{ typeOpen: false }" wire:key="book-type-{{ $selected->id }}">
                    <button type="button" x-on:click="typeOpen = true"
                            class="h-full w-full rounded border border-white/10 bg-sidebar px-3 py-2 text-left hover:border-link/40">
                        <div class="text-[10px] uppercase tracking-wide text-muted">Type</div>
                        <div class="text-lg font-semibold">{{ $selected->type ?: '—' }}</div>
                    </button>
                    <template x-teleport="body">
                        <div x-show="typeOpen" x-cloak x-transition.opacity
                             x-on:keydown.escape.window="typeOpen = false"
                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
                             x-on:click.self="typeOpen = false">
                            <div class="w-full max-w-xs rounded border border-white/10 bg-panel p-4 shadow-xl">
                                <div class="mb-3 text-xs uppercase tracking-wide text-muted">Type</div>
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach (\App\Livewire\Read::BOOK_TYPES as $bookType)
                                        <button type="button"
                                                x-on:click="$wire.setBookType({{ $selected->id }}, '{{ $bookType }}'); typeOpen = false"
                                                @class([
                                                    'rounded border py-3 text-lg font-semibold',
                                                    'border-link bg-link/10 text-body' => $selected->type === $bookType,
                                                    'border-white/10 bg-sidebar text-muted hover:text-body' => $selected->type !== $bookType,
                                                ])>{{ $bookType }}</button>
                                    @endforeach
                                </div>
                                <div class="mt-3 flex justify-between text-xs">
                                    @if ($selected->type)
                                        <button type="button" x-on:click="$wire.setBookType({{ $selected->id }}, null); typeOpen = false"
                                                class="text-muted hover:text-body">Clear</button>
                                    @else
                                        <span></span>
                                    @endif
                                    <button type="button" x-on:click="typeOpen = false" class="text-muted hover:text-body">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                @endunless
                <div class="grid flex-1 grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded border border-white/10 bg-sidebar px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wide text-muted">Total time read</div>
                    <div class="text-lg font-semibold tabular-nums" x-text="formatMinutes(bookMinutes())">{{ $bookTotals['totalDuration'] }}</div>
                </div>
                <div class="rounded border border-white/10 bg-sidebar px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wide text-muted">Read this week</div>
                    <div class="text-sm font-semibold">{{ $bookTotals['weekDays'] !== '' ? $bookTotals['weekDays'] : '—' }}</div>
                </div>
                <div class="rounded border border-white/10 bg-sidebar px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wide text-muted">Last read</div>
                    <div class="text-sm font-semibold">
                        @if ($bookTotals['lastRead'])
                            <span title="{{ $bookTotals['lastRead']->timezone($timezone)->format('j M Y, H:i') }}">{{ \App\Livewire\Read::relativeDay($bookTotals['lastRead']) }}</span>
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="flex flex-col justify-center gap-1 px-1 py-1">
                    @if ($selected->isStartedToday())
                        <button type="button" wire:click="clearStartedToday({{ $selected->id }})"
                                class="text-left text-xs text-muted hover:text-body">Clear 'started today'</button>
                    @else
                        <button type="button" wire:click="markStartedToday({{ $selected->id }})"
                                class="text-left text-xs text-muted hover:text-body">Mark as 'started today'</button>
                    @endif
                    @if ($selected->isFinished())
                        <button type="button" wire:click="markBookReading({{ $selected->id }})"
                                class="text-left text-xs text-muted hover:text-body">Mark as unfinished</button>
                    @else
                        <button type="button" wire:click="markBookFinished({{ $selected->id }})"
                                class="text-left text-xs text-muted hover:text-body">Mark as 'finished'</button>
                    @endif
                </div>
                @if ($selected->isFinished())
                    <button type="button"
                            wire:click="openFeedback({{ $selected->id }})"
                            class="read-feedback-btn col-span-2 sm:col-span-4 rounded px-3 py-2 text-sm font-semibold hover:brightness-105">
                        {{ $selected->hasFeedback() ? 'View feedback' : 'Give feedback' }}
                    </button>
                @endif
                </div>
            </div>

            <div class="mb-4 flex flex-wrap items-center justify-center gap-2">
                @if ($runningSession)
                    <button type="button" wire:click="stopSession({{ $runningSession->id }})"
                            class="rounded bg-mark px-3 py-1.5 text-sm font-medium text-black">
                        Stop session
                    </button>
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
                        @unless ($isArticles)
                        <div class="sm:w-28">
                            <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Pages</label>
                            <input type="number" min="0" wire:model="newSessionPagesRead" placeholder="—"
                                   class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                            @error('newSessionPagesRead') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        @endunless
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
                                    @unless ($isArticles)
                                    <div class="sm:w-28">
                                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Pages</label>
                                        <input type="number" min="0" wire:model="editSessionPagesRead"
                                               class="w-full rounded border border-white/10 bg-panel px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                                    </div>
                                    @endunless
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
                                        @if ($startedSessionId === $session->id)
                                            @include('livewire.partials.read-started-icon', ['startedLabel' => 'Started'])
                                        @endif
                                        {{ $session->started_at->timezone($timezone)->format('D j M Y, H:i') }}
                                        @if ($session->isRunning())
                                            <span class="ml-1 rounded bg-mark/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-mark">running</span>
                                            <span class="ml-2 font-semibold tabular-nums text-mark"
                                                  x-data="elapsedClock(@js($session->started_at->utc()->toIso8601String()))"
                                                  x-init="start()"
                                                  x-text="display">00:00:00</span>
                                        @else
                                            @php
                                                $finishLocal = $session->finished_at->copy()->timezone($timezone);
                                                $finishNextDay = $finishLocal->toDateString() !== $session->started_at->copy()->timezone($timezone)->toDateString();
                                            @endphp
                                            <span class="text-muted"
                                                  wire:key="finish-{{ $session->id }}-{{ $session->finished_at->timestamp }}"
                                                  x-data="inlineFinish({{ $session->id }}, @js($finishLocal->format('H:i')))">
                                                <span x-show="!editing"
                                                      x-on:dblclick.prevent="open()"
                                                      x-on:touchend="tap($event)"
                                                      data-finish-edit
                                                      title="Double-click to edit finish time"
                                                      class="cursor-text select-none rounded px-0.5 hover:bg-white/5 hover:text-body"
                                                      style="touch-action: manipulation; -webkit-user-select: none;">→ {{ $finishLocal->format('H:i') }}@if ($finishNextDay)<sup class="ml-0.5 text-[10px] text-faint" title="Next day">+1</sup>@endif</span>
                                                <span x-show="editing" x-cloak class="inline-flex items-center gap-1">→
                                                    <input type="time" x-ref="input" x-model="value" aria-label="Finish time"
                                                           x-on:keydown.enter.prevent="save()"
                                                           x-on:keydown.escape.prevent="cancel()"
                                                           x-on:blur="save()"
                                                           x-bind:disabled="saving"
                                                           class="rounded border border-white/10 bg-panel px-1.5 py-0.5 text-sm tabular-nums text-body focus:border-link focus:outline-none" />
                                                </span>
                                                <span x-show="error" x-cloak x-text="error" class="ml-1 text-xs text-red-400"></span>
                                            </span>
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
                                    @if ($editMode)
                                        <button type="button" wire:click="startEditSession({{ $session->id }})"
                                                class="text-xs text-muted hover:text-body">edit</button>
                                        <button type="button"
                                                wire:click="deleteSession({{ $session->id }})"
                                                wire:confirm="Delete this session?"
                                                class="text-xs text-red-400/80 hover:text-red-400">delete</button>
                                    @endif
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
                applyTotals(t) {
                    if (!t) return;
                    this.todayBase = t.todayBase || 0;
                    this.weekBase = t.weekBase || 0;
                    this.bookBase = t.bookBase || 0;
                    this.todayRuns = t.todayRuns || [];
                    this.weekRuns = t.weekRuns || [];
                    this.bookRuns = t.bookRuns || [];
                    this.tick = Date.now();
                },
            };
        }

        // Inline finish-time editor: double-click (desktop) or double-tap (touch) "→ HH:MM".
        // Enter / blur saves, Esc cancels. Server validates (finish > start, overnight ≤ 12h).
        function inlineFinish(id, original) {
            return {
                id,
                original,
                value: original,
                editing: false,
                saving: false,
                error: '',
                lastTap: 0,
                open() {
                    if (this.editing) return;
                    this.value = this.original;
                    this.error = '';
                    this.editing = true;
                    this.$nextTick(() => { this.$refs.input && this.$refs.input.focus(); });
                },
                tap(e) {
                    const now = Date.now();
                    if (now - this.lastTap < 350) {
                        e.preventDefault();
                        this.lastTap = 0;
                        this.open();
                    } else {
                        this.lastTap = now;
                    }
                },
                cancel() {
                    this.editing = false;
                    this.error = '';
                    this.value = this.original;
                },
                async save() {
                    if (!this.editing || this.saving) return;
                    if (!this.value || this.value === this.original) { this.cancel(); return; }
                    this.saving = true;
                    try {
                        const r = await this.$wire.updateSessionFinish(this.id, this.value);
                        if (r && r.ok) {
                            this.original = r.finish || this.value;
                            this.editing = false;
                            this.error = '';
                        } else {
                            this.error = (r && r.error) || 'Could not save.';
                        }
                    } catch (e) {
                        this.error = 'Could not save.';
                    } finally {
                        this.saving = false;
                    }
                },
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
    <livewire:read-book-stats-modal />
    @if ($feedbackOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-data="{
                tab: 'description',
                leave(fn) {
                    if (! $wire.feedbackDirty || confirm('Discard unsaved changes?')) {
                        fn();
                    }
                },
                saved: false,
                savedTimer: null,
                flashSaved() {
                    this.saved = false;
                    this.$nextTick(() => {
                        this.saved = true;
                        clearTimeout(this.savedTimer);
                        this.savedTimer = setTimeout(() => { this.saved = false }, 1000);
                    });
                }
             }"
             x-on:book-feedback-saved.window="flashSaved()"
             x-on:keydown.escape.window="leave(() => $wire.closeFeedback())"
             role="dialog"
             aria-modal="true"
             aria-labelledby="read-feedback-title">
            <div class="absolute inset-0 bg-black/60" x-on:click="leave(() => $wire.closeFeedback())" aria-hidden="true"></div>

            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg border border-white/10 bg-sidebar p-4 shadow-xl sm:p-5"
                 :class="{ 'modal-saved-glow': saved }">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <h2 id="read-feedback-title" class="text-sm font-semibold uppercase tracking-[0.16em] text-muted">
                        Feedback · {{ $feedbackBookTitle }}
                    </h2>
                    <button type="button"
                            x-on:click="leave(() => $wire.closeFeedback())"
                            class="rounded px-1 text-lg leading-none text-muted hover:text-body"
                            aria-label="Close">×</button>
                </div>

                <div class="mb-4 grid grid-cols-3 gap-1 rounded-lg border border-white/10 bg-panel p-1" role="tablist" aria-label="Feedback sections">
                    <button type="button"
                            role="tab"
                            :aria-selected="tab === 'description'"
                            x-on:click="tab = 'description'"
                            :class="tab === 'description' ? 'bg-sidebar text-body' : 'text-muted hover:text-body'"
                            class="inline-flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M8 1.8A4.2 4.2 0 0 0 5.2 8c.3.6.8 1.1 1.1 1.7.2.4.2.8.2 1.2h3c0-.4 0-.8.2-1.2.3-.6.8-1.1 1.1-1.7A4.2 4.2 0 0 0 8 1.8Z" stroke-linejoin="round"/>
                            <path d="M6.5 12.8h3M7 14.2h2" stroke-linecap="round"/>
                        </svg>
                        Description
                    </button>
                    <button type="button"
                            role="tab"
                            :aria-selected="tab === 'like'"
                            x-on:click="tab = 'like'"
                            :class="tab === 'like' ? 'bg-sidebar text-body' : 'text-muted hover:text-body'"
                            class="inline-flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M5.2 7.2v6.3H3.4A1.2 1.2 0 0 1 2.2 12.3V8.4A1.2 1.2 0 0 1 3.4 7.2h1.8Z" stroke-linejoin="round"/>
                            <path d="M5.2 7.2 7 3.4c.3-.6.9-.9 1.5-.8.7.1 1.2.7 1.2 1.4V6h3c.8 0 1.4.7 1.3 1.5l-.7 5.2a1.3 1.3 0 0 1-1.3 1.1H5.2" stroke-linejoin="round" stroke-linecap="round"/>
                        </svg>
                        Like
                    </button>
                    <button type="button"
                            role="tab"
                            :aria-selected="tab === 'dislike'"
                            x-on:click="tab = 'dislike'"
                            :class="tab === 'dislike' ? 'bg-sidebar text-body' : 'text-muted hover:text-body'"
                            class="inline-flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M5.2 8.8V2.5H3.4A1.2 1.2 0 0 0 2.2 3.7v3.9A1.2 1.2 0 0 0 3.4 8.8h1.8Z" stroke-linejoin="round"/>
                            <path d="M5.2 8.8 7 12.6c.3.6.9.9 1.5.8.7-.1 1.2-.7 1.2-1.4V10h3c.8 0 1.4-.7 1.3-1.5l-.7-5.2A1.3 1.3 0 0 0 12 2.2H5.2" stroke-linejoin="round" stroke-linecap="round"/>
                        </svg>
                        Dislike
                    </button>
                </div>

                <div x-show="tab === 'description'" role="tabpanel">
                    <label class="block">
                        <span class="sr-only">Description</span>
                        <textarea wire:model.live="feedbackDescription"
                                  rows="10"
                                  class="w-full resize-y rounded border border-white/10 bg-panel px-3 py-2 text-sm text-body focus:border-link focus:outline-none"
                                  placeholder="Write a description…"></textarea>
                    </label>
                </div>

                <div x-show="tab === 'like'" role="tabpanel">
                    <div class="space-y-2">
                        @forelse ($feedbackLikes as $i => $entry)
                            <div wire:key="feedback-like-{{ $entry['id'] ?? 'new-'.$i }}" class="flex items-start gap-2">
                                <label class="block min-w-0 flex-1">
                                    <span class="sr-only">Like {{ $i + 1 }}</span>
                                    <textarea wire:model.live="feedbackLikes.{{ $i }}.body"
                                              rows="2"
                                              class="w-full resize-y rounded border border-white/10 bg-panel px-3 py-2 text-sm text-body focus:border-link focus:outline-none"
                                              placeholder="What did you like?"></textarea>
                                </label>
                                <button type="button"
                                        wire:click="removeFeedbackEntry('like', {{ $i }})"
                                        class="mt-1 rounded px-1 text-lg leading-none text-muted hover:text-red-400"
                                        aria-label="Remove like"
                                        title="Remove">×</button>
                            </div>
                        @empty
                            <p class="text-sm text-muted">No likes yet.</p>
                        @endforelse
                    </div>
                    <button type="button"
                            wire:click="addFeedbackEntry('like')"
                            class="mt-3 inline-flex h-8 w-8 items-center justify-center rounded border border-white/10 text-lg leading-none text-muted hover:text-body"
                            aria-label="Add like"
                            title="Add like">+</button>
                </div>

                <div x-show="tab === 'dislike'" role="tabpanel">
                    <div class="space-y-2">
                        @forelse ($feedbackDislikes as $i => $entry)
                            <div wire:key="feedback-dislike-{{ $entry['id'] ?? 'new-'.$i }}" class="flex items-start gap-2">
                                <label class="block min-w-0 flex-1">
                                    <span class="sr-only">Dislike {{ $i + 1 }}</span>
                                    <textarea wire:model.live="feedbackDislikes.{{ $i }}.body"
                                              rows="2"
                                              class="w-full resize-y rounded border border-white/10 bg-panel px-3 py-2 text-sm text-body focus:border-link focus:outline-none"
                                              placeholder="What did you dislike?"></textarea>
                                </label>
                                <button type="button"
                                        wire:click="removeFeedbackEntry('dislike', {{ $i }})"
                                        class="mt-1 rounded px-1 text-lg leading-none text-muted hover:text-red-400"
                                        aria-label="Remove dislike"
                                        title="Remove">×</button>
                            </div>
                        @empty
                            <p class="text-sm text-muted">No dislikes yet.</p>
                        @endforelse
                    </div>
                    <button type="button"
                            wire:click="addFeedbackEntry('dislike')"
                            class="mt-3 inline-flex h-8 w-8 items-center justify-center rounded border border-white/10 text-lg leading-none text-muted hover:text-body"
                            aria-label="Add dislike"
                            title="Add dislike">+</button>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="button"
                            wire:click="saveFeedback"
                            class="rounded border border-white/15 bg-panel px-4 py-1.5 text-sm font-medium hover:border-link/40">
                        Save
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
 
