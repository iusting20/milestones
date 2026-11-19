<div class="mx-auto flex max-w-6xl flex-col gap-6 px-3 py-6 sm:px-4"
     x-data="readLiveTotals(@js($liveTotals))"
     x-init="start()"
     x-on:read-live-totals.window="applyTotals($event.detail.totals)"
     x-on:book-reader-opened.window="window.scrollTo({ top: 0, behavior: 'smooth' })">
    {{-- Book Stack Section (hidden when focused directly inside a book) --}}
    @unless ($readerOpen && $selected)
    <section class="w-full" x-data="{ hovered: null }">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-baseline gap-3">
                <h1 class="text-xl font-bold text-body">{{ $isArticles ? 'Articles' : 'Books' }}</h1>
                @if ($isArticles)
                    <a href="{{ route('read') }}" class="text-xs text-faint no-underline hover:text-muted">books</a>
                @else
                    <a href="{{ route('read.articles') }}" class="text-xs text-faint no-underline hover:text-muted">articles</a>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
                <div class="flex items-center gap-2 rounded border border-white/10 bg-sidebar px-2.5 py-1">
                    <span class="uppercase tracking-wide text-[10px] text-faint">This week</span>
                    <span class="font-semibold text-body tabular-nums" x-text="formatMinutes(weekMinutes())">{{ $pageTotals['weekDuration'] }}</span>
                </div>
                <div class="flex items-center gap-2 rounded border border-white/10 bg-sidebar px-2.5 py-1">
                    <span class="uppercase tracking-wide text-[10px] text-faint">Today</span>
                    <span class="font-semibold text-body tabular-nums" x-text="formatMinutes(todayMinutes())">{{ $pageTotals['todayDuration'] }}</span>
                </div>
                <a href="{{ route('read.stats') }}" class="px-1.5 text-sm font-semibold text-mark no-underline hover:opacity-80">Stats</a>
                <button type="button" wire:click="startAddBook"
                        class="rounded border border-white/10 px-2.5 py-1 text-xs text-link hover:bg-panel">
                    + {{ $isArticles ? 'Article' : 'Book' }}
                </button>
            </div>
        </div>

        @if ($addingBook)
            <form wire:submit="addBook"
                  x-data="pdfCoverExtractor('newBookGeneratedCover')"
                  class="mb-4 space-y-3 rounded-lg border border-white/10 bg-panel p-4">
                <div class="grid gap-2.5 sm:grid-cols-4">
                    <div>
                        <input type="text" wire:model="newBookTitle" placeholder="{{ $isArticles ? 'Name' : 'Title' }}" required
                               class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                        @error('newBookTitle') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input type="text" wire:model="newBookAuthor" placeholder="Author (optional)"
                               class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                    </div>
                    <div>
                        <input type="text" wire:model="newBookPublisher" placeholder="Publisher (e.g. Penguin, Oxford)"
                               class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                    </div>
                    @unless ($isArticles)
                    <div>
                        <input type="number" min="0" wire:model="newBookTotalPages" placeholder="Total pages (optional)"
                               class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                        @error('newBookTotalPages') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    @endunless
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">PDF File (optional — auto-extracts Page 1 cover)</label>
                        <input type="file"
                               accept="application/pdf,.pdf"
                               wire:model="newBookPdf"
                               x-on:change="extract($event)"
                               class="w-full rounded border border-white/10 bg-sidebar px-2.5 py-1.5 text-xs text-muted file:mr-2.5 file:rounded file:border-0 file:bg-white/10 file:px-2.5 file:py-1 file:text-xs file:text-body hover:file:bg-white/15" />
                        @error('newBookPdf') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        <p x-show="status" x-cloak x-text="status" class="mt-1 text-[11px] text-muted"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Cover Image (optional override)</label>
                        <input type="file"
                               accept="image/*"
                               wire:model="newBookCover"
                               class="w-full rounded border border-white/10 bg-sidebar px-2.5 py-1.5 text-xs text-muted file:mr-2.5 file:rounded file:border-0 file:bg-white/10 file:px-2.5 file:py-1 file:text-xs file:text-body hover:file:bg-white/15" />
                        @error('newBookCover') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <template x-if="preview">
                        <img :src="preview" alt="Extracted cover preview" class="h-14 w-10 rounded border border-zinc-700 object-cover" />
                    </template>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="newBookPdf,newBookCover,addBook"
                            class="rounded bg-check px-3.5 py-1.5 text-sm font-medium text-white disabled:opacity-50">
                        <span wire:loading.remove wire:target="newBookPdf,newBookCover">Add</span>
                        <span wire:loading wire:target="newBookPdf,newBookCover">Uploading…</span>
                    </button>
                    <button type="button" wire:click="cancelForms" class="text-sm text-muted hover:text-body">Cancel</button>
                </div>
            </form>
        @endif

        {{-- Recessed Bookcase Bay + Page-Wide Vertical Book Stack (Physical Spines) --}}
        <div class="relative w-full rounded-xl border border-white/[0.06] bg-gradient-to-b from-[#131110] via-[#171412] to-[#110f0d] px-4 pt-6 pb-4 shadow-[inset_0_12px_36px_rgba(0,0,0,0.85),inset_24px_0_32px_-16px_rgba(0,0,0,0.75),inset_-24px_0_32px_-16px_rgba(0,0,0,0.75)] sm:px-8">
            <ul class="flex w-full flex-col items-center gap-[1px]" x-data="bookSortList()">
                @forelse ($books as $book)
                    @php
                        $stackLayouts = [
                            'w-full',
                            'w-[98%] -translate-x-1',
                            'w-[96.5%] translate-x-1',
                            'w-[99%] -translate-x-0.5',
                            'w-[97.5%] translate-x-0.5',
                        ];
                        $stackLayout = $stackLayouts[$book->id % count($stackLayouts)];
                        $isLastRead = $lastReadBookId === $book->id;
                        $hoverParts = array_values(array_filter([
                            $book->title,
                            $book->author,
                            $book->publisher,
                            ($book->current_page && $book->total_pages) ? 'p. '.$book->current_page.' / '.$book->total_pages : ($book->total_pages ? number_format($book->total_pages).' pages' : null),
                            $book->latestSession ? 'Last read '.\App\Livewire\Read::relativeDay($book->latestSession->started_at) : null,
                        ]));
                        $hoverSummary = implode(' · ', $hoverParts);
                    @endphp
                    <li wire:key="book-{{ $book->id }}"
                        data-sort-item="{{ $book->id }}"
                        x-on:mouseenter="hovered = @js($hoverSummary)"
                        x-on:mouseleave="hovered = null"
                        class="group relative {{ $stackLayout }}">
                        <span data-drag-handle
                              x-on:click.stop
                              aria-label="Drag to reorder"
                              class="absolute right-2 top-1/2 z-20 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded bg-black/70 text-zinc-200 opacity-0 backdrop-blur-sm transition hover:bg-black hover:text-white group-hover:opacity-100"
                              style="cursor:grab; touch-action:none; -webkit-user-select:none; user-select:none;">
                            @include('livewire.partials.drag-handle')
                        </span>
                        @include('livewire.partials.book-cover-card', [
                            'book' => $book,
                            'selectedId' => $selected?->id,
                            'finished' => false,
                        ])
                        @if ($isLastRead)
                            {{-- Crimson silk ribbon bookmark peeking from the most-recently-read spine --}}
                            <span data-silk-ribbon
                                  title="Last read"
                                  aria-label="Last read bookmark"
                                  class="pointer-events-none absolute -bottom-3 left-9 z-30 block h-4 w-2.5 bg-gradient-to-b from-[#991b1b] via-[#b91c1c] to-[#7f1d1d] shadow-[0_3px_6px_rgba(0,0,0,0.75)]"
                                  style="clip-path: polygon(0 0, 100% 0, 100% 100%, 50% 70%, 0 100%);"></span>
                        @endif
                    </li>
                @empty
                    <li class="w-full rounded-lg border border-dashed border-white/15 px-4 py-8 text-center text-sm text-muted">
                        @if ($finishedBooks->isNotEmpty())
                            No open {{ $isArticles ? 'articles' : 'books' }}.
                        @else
                            No {{ $isArticles ? 'articles' : 'books' }} yet. Add one to start tracking.
                        @endif
                    </li>
                @endforelse
            </ul>

            @if ($books->isNotEmpty())
                {{-- Warm walnut wooden shelf plank anchoring the vertical book stack --}}
                <div class="mt-0.5 h-4 w-full rounded-sm border-t border-amber-200/20 bg-gradient-to-b from-[#6b4423] via-[#472b14] to-[#241409] shadow-[0_14px_30px_rgba(0,0,0,0.85)]"
                     aria-hidden="true"></div>
            @endif

            {{-- Quiet hover status line beneath the wooden plank --}}
            <div class="mt-2.5 flex min-h-[1.25rem] items-center justify-center text-center text-xs text-muted">
                <span x-show="hovered" x-cloak x-text="hovered" x-transition.opacity.duration.150ms class="truncate"></span>
            </div>
        </div>

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
                                direction: 'vertical',
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
            <div class="mt-4">
                <button type="button" wire:click="$toggle('showFinished')"
                        class="flex items-center gap-1 text-xs text-muted no-underline hover:text-body"
                        aria-expanded="{{ $showFinished ? 'true' : 'false' }}">
                    <span class="inline-block w-3 text-[10px]">{{ $showFinished ? '▾' : '▸' }}</span>
                    Finished ({{ $finishedBooks->count() }})
                </button>
                @if ($showFinished)
                    <ul class="mt-2 flex w-full flex-col items-center gap-[3px]" data-finished-list>
                        @foreach ($finishedBooks as $book)
                            <li wire:key="finished-book-{{ $book->id }}" data-finished-item="{{ $book->id }}" class="w-full">
                                @include('livewire.partials.book-cover-card', [
                                    'book' => $book,
                                    'selectedId' => $selected?->id,
                                    'finished' => true,
                                ])
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </section>
    @endunless

    {{-- Focused Inline Reader + Single-Row Slim Reading Bar + Adobe Read Mode + Highlights --}}
    @if ($selected)
        @php
            $initialPage = $selected->current_page ?: 1;
            $highlightsJson = $highlights->map(fn ($h) => [
                'id' => $h->id,
                'page' => $h->page_number,
                'quote' => $h->quote,
                'color' => $h->color,
            ])->values()->all();
        @endphp
        <section wire:key="reader-shell-{{ $selected->id }}"
                 x-data="pdfBookReader({
                     pdfUrl: @js($selected->pdfUrl()),
                     initialPage: @js($initialPage),
                     bookId: @js($selected->id),
                     stopUrl: @js(route('read.sessions.stop-active')),
                     activeBookId: @js($runningSession && $readerOpen ? $selected->id : null),
                     highlights: @js($highlightsJson),
                 })"
                 x-init="init()"
                 x-on:reader-jump-page.window="goToPage($event.detail.page)"
                 x-on:keydown.window="handleKey($event)"
                 :class="readMode
                     ? (theme === 'dark' ? 'fixed inset-0 z-50 flex flex-col bg-[#0f0f11] p-3 sm:p-4 overflow-hidden' : 'fixed inset-0 z-50 flex flex-col bg-[#e8e4dc] p-3 sm:p-4 overflow-hidden')
                     : 'w-full min-w-0 rounded-xl border border-zinc-800 bg-panel/40 p-3 sm:p-4'">
            {{-- Single-Row Slim Reading Bar (Title + Bookmark Page + Dark/Light + Read Mode + Highlights + Timer) --}}
            <div :class="readMode
                     ? 'mb-2 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-800 bg-sidebar/90 px-3 py-1.5 opacity-80 shadow-lg backdrop-blur transition-opacity hover:opacity-100'
                     : 'sticky top-12 z-20 mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-800 bg-sidebar/95 px-3 py-2 shadow-md backdrop-blur'">
                {{-- Left: Back + Cover Thumb + Title --}}
                <div class="flex min-w-0 flex-1 items-center gap-2.5">
                    <template x-if="readMode">
                        <button type="button"
                                x-on:click="readMode = false"
                                title="Exit Read Mode (Esc)"
                                class="shrink-0 rounded border border-zinc-700 bg-panel px-2.5 py-1 text-xs font-medium text-body transition hover:border-zinc-500">
                            ✕ Exit Read Mode
                        </button>
                    </template>
                    <button type="button"
                            x-show="!readMode"
                            wire:click="closeReader"
                            title="Close reader and stop active session (Esc)"
                            class="shrink-0 rounded border border-zinc-700 bg-panel px-2.5 py-1 text-xs font-medium text-body transition hover:border-zinc-500">
                        ← Close Reader
                    </button>
                    <span class="hidden h-4 w-px shrink-0 bg-white/10 sm:inline-block" aria-hidden="true"></span>
                    @if ($selected->coverUrl())
                        <img src="{{ $selected->coverUrl() }}"
                             alt="{{ $selected->title }} cover"
                             class="hidden aspect-[2/3] w-6 shrink-0 rounded-sm border border-white/15 object-cover sm:block" />
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex min-w-0 items-baseline gap-2">
                            <h2 class="truncate font-serif text-base font-bold text-body sm:text-lg">@include('livewire.partials.read-item-name', ['title' => $selected->title, 'startedToday' => $selected->isStartedToday(), 'finished' => $selected->isFinished(), 'truncate' => true])</h2>
                            @if ($selected->author)
                                <span class="hidden truncate text-xs text-muted lg:inline">{{ $selected->author }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Right: Page Nav + Theme + Read Mode + Highlights + Timer + Actions --}}
                <div class="flex flex-wrap items-center justify-end gap-1.5 sm:gap-2">
                    @unless ($isArticles)
                        <form wire:submit="saveCurrentPage" class="flex items-center gap-1 rounded border border-white/10 bg-panel px-1.5 py-0.5 text-xs text-muted">
                            @if ($selected->pdfUrl())
                                <button type="button" x-on:click="prevPage()" :disabled="page <= 1"
                                        class="px-1 text-zinc-400 hover:text-body disabled:opacity-30" title="Previous page (←)">‹</button>
                            @endif
                            <label for="reader-page-{{ $selected->id }}" class="text-[10px] uppercase tracking-wide text-faint">p.</label>
                            <input id="reader-page-{{ $selected->id }}"
                                   type="number"
                                   min="1"
                                   @if ($selected->total_pages) max="{{ $selected->total_pages }}" @endif
                                   wire:model="readerCurrentPage"
                                   wire:change="saveCurrentPage"
                                   placeholder="1"
                                   aria-label="Current page"
                                   class="w-11 bg-transparent text-center font-mono text-xs text-body tabular-nums focus:outline-none" />
                            @if ($selected->total_pages)
                                <span class="text-[11px] tabular-nums text-faint">/ {{ $selected->total_pages }}</span>
                            @endif
                            @if ($selected->pdfUrl())
                                <button type="button" x-on:click="nextPage()" :disabled="numPages > 0 && page >= numPages"
                                        class="px-1 text-zinc-400 hover:text-body disabled:opacity-30" title="Next page (→)">›</button>
                            @endif
                        </form>
                    @endunless

                    @if ($selected->pdfUrl())
                        {{-- Dark / Light Reading Mode Toggle (defaults to Dark) --}}
                        <button type="button"
                                x-on:click="toggleTheme()"
                                data-theme-toggle
                                class="rounded border border-white/10 bg-panel px-2 py-1 text-xs text-body transition hover:border-zinc-500"
                                title="Toggle Dark / Light reading mode">
                            <span x-show="theme === 'dark'">☀ Light</span>
                            <span x-show="theme === 'light'" x-cloak>🌙 Dark</span>
                        </button>

                        {{-- Adobe-style Liquid / Reflow Read Mode Toggle --}}
                        <button type="button"
                                x-on:click="toggleReadMode()"
                                data-read-mode-toggle
                                :class="readMode ? 'border-link/50 bg-link/15 text-link' : 'border-white/10 bg-panel text-muted hover:text-body'"
                                class="rounded border px-2 py-1 text-xs transition"
                                title="Toggle Adobe-style reflow Read Mode (R)">
                            <span x-show="!readMode">Read Mode</span>
                            <span x-show="readMode" x-cloak>Page View</span>
                        </button>

                        {{-- Font size controls (visible in Liquid Read Mode) --}}
                        <div x-show="readMode" x-cloak class="flex items-center gap-0.5 rounded border border-white/10 bg-panel px-1 py-0.5 text-xs text-muted">
                            <button type="button" x-on:click="smallerFont()" class="px-1.5 py-0.5 hover:text-body" title="Smaller text">A-</button>
                            <button type="button" x-on:click="largerFont()" class="px-1.5 py-0.5 hover:text-body" title="Larger text">A+</button>
                        </div>
                    @endif

                    {{-- Saved Highlights Drawer Toggle --}}
                    <button type="button"
                            wire:click="$toggle('showHighlights')"
                            data-highlights-toggle
                            @class([
                                'rounded border px-2 py-1 text-xs transition',
                                'border-amber-400/50 bg-amber-400/15 text-amber-300' => $showHighlights,
                                'border-white/10 bg-panel text-muted hover:text-body' => ! $showHighlights,
                            ])
                            title="Show saved highlights">
                        Highlights{{ $highlights->isNotEmpty() ? ' ('.$highlights->count().')' : '' }}
                    </button>

                    <div class="flex items-center gap-2 rounded border border-white/10 bg-panel px-2.5 py-1">
                        <span class="hidden text-[10px] uppercase tracking-wider text-faint xl:inline">Total time read</span>
                        <span class="text-xs font-semibold text-body tabular-nums" x-text="formatMinutes(bookMinutes())">{{ $bookTotals['totalDuration'] }}</span>
                        @if ($runningSession)
                            <span class="h-3 w-px bg-white/15" aria-hidden="true"></span>
                            <span class="font-mono text-xs font-semibold tabular-nums text-link"
                                  wire:key="docked-running-{{ $runningSession->id }}"
                                  x-data="elapsedClock(@js($runningSession->started_at->utc()->toIso8601String()))"
                                  x-init="start()"
                                  x-text="display">00:00:00</span>
                        @endif
                    </div>

                    @if ($runningSession)
                        <button type="button" wire:click="stopSession({{ $runningSession->id }})"
                                class="rounded bg-mark px-2.5 py-1 text-xs font-medium text-black transition hover:opacity-90">
                            Stop session
                        </button>
                    @else
                        <button type="button" wire:click="startSessionNow"
                                class="rounded bg-check px-2.5 py-1 text-xs font-medium text-white transition hover:opacity-90">
                            Start session now
                        </button>
                    @endif

                    <button type="button" x-show="!readMode" wire:click="startAddSession"
                            class="rounded border border-white/10 px-2 py-1 text-xs text-link transition hover:bg-panel">
                        + Log session
                    </button>

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
                            x-show="!readMode"
                            wire:click="$toggle('editMode')"
                            @class([
                                'flex h-7 w-7 items-center justify-center rounded text-muted transition hover:bg-white/5 hover:text-body',
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
                <form wire:submit="saveEditBook"
                      x-show="!readMode"
                      x-data="pdfCoverExtractor('editBookGeneratedCover')"
                      class="mb-4 space-y-3 rounded-lg border border-white/10 bg-panel p-3.5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="sm:w-48">
                            <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">{{ $isArticles ? 'Name' : 'Title' }}</label>
                            <input type="text" wire:model="editBookTitle"
                                   class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                            @error('editBookTitle') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:w-40">
                            <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Author</label>
                            <input type="text" wire:model="editBookAuthor"
                                   class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                        </div>
                        <div class="sm:w-40">
                            <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Publisher</label>
                            <input type="text" wire:model="editBookPublisher" placeholder="e.g. Penguin, Oxford"
                                   class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                        </div>
                        @unless ($isArticles)
                        <div class="sm:w-28">
                            <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Pages</label>
                            <input type="number" min="0" wire:model="editBookTotalPages"
                                   class="w-full rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                            @error('editBookTotalPages') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
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
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">
                                {{ $selected->pdf_path ? 'Replace PDF (optional)' : 'Attach PDF (optional)' }}
                            </label>
                            <input type="file"
                                   accept="application/pdf,.pdf"
                                   wire:model="editBookPdf"
                                   x-on:change="extract($event)"
                                   class="w-full rounded border border-white/10 bg-sidebar px-2.5 py-1.5 text-xs text-muted file:mr-2.5 file:rounded file:border-0 file:bg-white/10 file:px-2.5 file:py-1 file:text-xs file:text-body hover:file:bg-white/15" />
                            @error('editBookPdf') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            <p x-show="status" x-cloak x-text="status" class="mt-1 text-[11px] text-muted"></p>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] uppercase tracking-wide text-muted">
                                {{ $selected->cover_path ? 'Replace Cover Image (optional)' : 'Upload Cover Image (optional)' }}
                            </label>
                            <input type="file"
                                   accept="image/*"
                                   wire:model="editBookCover"
                                   class="w-full rounded border border-white/10 bg-sidebar px-2.5 py-1.5 text-xs text-muted file:mr-2.5 file:rounded file:border-0 file:bg-white/10 file:px-2.5 file:py-1 file:text-xs file:text-body hover:file:bg-white/15" />
                            @error('editBookCover') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <template x-if="preview">
                            <img :src="preview" alt="Extracted cover preview" class="h-14 w-10 rounded border border-zinc-700 object-cover" />
                        </template>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="editBookPdf,editBookCover,saveEditBook"
                                class="rounded bg-check px-3.5 py-1.5 text-sm font-medium text-white disabled:opacity-50">
                            <span wire:loading.remove wire:target="editBookPdf,editBookCover">Save</span>
                            <span wire:loading wire:target="editBookPdf,editBookCover">Uploading…</span>
                        </button>
                        <button type="button" wire:click="cancelForms" class="text-sm text-muted hover:text-body">Cancel</button>
                    </div>
                </form>
            @endif

            @if ($addingSession)
                <form wire:submit="addSession" x-show="!readMode" class="mb-4 space-y-2 rounded-lg border border-white/10 bg-panel p-3">
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

            {{-- Reader Viewport + Optional Highlights Drawer --}}
            <div :class="readMode ? 'relative flex min-h-0 flex-1 gap-3 overflow-hidden' : 'relative mb-5 flex flex-col gap-3 lg:flex-row'">
                {{-- PDF Canvas + Selectable Text Layer (with Dark/Light mode + Highlight Popover) --}}
                <div class="relative min-w-0 flex-1"
                     data-pdf-reader
                     data-pdf-url="{{ $selected->pdfUrl() ?? '' }}">
                    @if ($selected->pdfUrl())
                        <div x-show="!fallbackIframe"
                             :class="[
                                 readMode ? 'h-full' : 'h-[84vh]',
                                 theme === 'dark' ? 'bg-[#111114] border-zinc-800' : 'bg-[#e6e2da] border-zinc-300',
                             ]"
                             class="relative flex flex-col items-center overflow-auto rounded-lg border p-3 sm:p-6">
                            {{-- Subtle zoom + page bar inside viewport (Page View only) --}}
                            <div x-show="!readMode" class="mb-3 flex w-full max-w-3xl items-center justify-between text-[11px]"
                                 :class="theme === 'dark' ? 'text-zinc-400' : 'text-zinc-600'">
                                <span>Select any text to highlight</span>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" x-on:click="zoomOut()" class="rounded border border-current/20 px-1.5 py-0.5 hover:opacity-80">−</button>
                                    <span class="tabular-nums" x-text="Math.round(scale * 100) + '%'">135%</span>
                                    <button type="button" x-on:click="zoomIn()" class="rounded border border-current/20 px-1.5 py-0.5 hover:opacity-80">+</button>
                                    <button type="button" x-on:click="fallbackIframe = true" class="ml-2 underline opacity-70 hover:opacity-100">Native PDF</button>
                                </div>
                            </div>

                            {{-- Toast feedback when a highlight is saved --}}
                            <div x-show="toast" x-cloak x-transition.opacity
                                 class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-full border border-amber-400/30 bg-zinc-900/95 px-3.5 py-1.5 text-xs font-medium text-amber-300 shadow-lg">
                                <span x-text="toast"></span>
                            </div>

                            {{-- Loading indicator --}}
                            <div x-show="loading" class="py-24 text-sm text-muted">Loading page…</div>

                            {{-- Adobe Liquid / Reflow Read Mode (Fits screen cleanly, crisp serif typography, native selection highlighting) --}}
                            <div x-show="readMode && !loading"
                                 x-cloak
                                 x-ref="reflowWrap"
                                 x-on:mouseup="onTextSelect($event, 'reflow')"
                                 x-on:touchend="onTextSelect($event, 'reflow')"
                                 :class="theme === 'dark' ? 'text-[#e7e2d8]' : 'text-[#1c1b18]'"
                                 class="relative mx-auto w-full max-w-2xl px-3 py-4 sm:px-8 sm:py-6">
                                <article class="font-serif leading-[1.85] tracking-[0.01em] selection:bg-amber-400/35"
                                         :style="`font-size: ${readFontSize}px;`">
                                    <template x-for="(block, idx) in reflowBlocks" :key="idx">
                                        <div>
                                            <h3 x-show="block.type === 'heading'"
                                                x-html="renderHighlightedHtml(block.text)"
                                                class="mb-4 mt-6 font-bold leading-snug tracking-tight"
                                                :style="`font-size: ${Math.round(readFontSize * 1.35)}px;`"></h3>
                                            <p x-show="block.type === 'paragraph'"
                                               x-html="renderHighlightedHtml(block.text)"
                                               class="mb-4 indent-4 sm:indent-6"></p>
                                        </div>
                                    </template>
                                    <p x-show="reflowBlocks.length === 0" class="py-12 text-center text-sm opacity-60">
                                        No reflowable text detected on this page — switch to Page View to inspect graphics.
                                    </p>
                                </article>

                                {{-- Floating Highlight Popover inside Liquid Read Mode --}}
                                <div x-show="selection.open && readMode"
                                     x-cloak
                                     x-transition.opacity.duration.120ms
                                     :style="`left:${selection.x}px;top:${selection.y}px;`"
                                     class="absolute z-40 w-64 -translate-x-1/2 -translate-y-full rounded-lg border border-zinc-700 bg-zinc-900/95 p-2.5 text-xs text-zinc-100 shadow-2xl backdrop-blur">
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400">Highlight p.<span x-text="page"></span></span>
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" x-on:click="selection.color = 'yellow'" :class="selection.color === 'yellow' ? 'ring-2 ring-white' : ''" class="h-4 w-4 rounded-full bg-amber-300" aria-label="Yellow"></button>
                                            <button type="button" x-on:click="selection.color = 'green'" :class="selection.color === 'green' ? 'ring-2 ring-white' : ''" class="h-4 w-4 rounded-full bg-emerald-400" aria-label="Green"></button>
                                            <button type="button" x-on:click="selection.color = 'blue'" :class="selection.color === 'blue' ? 'ring-2 ring-white' : ''" class="h-4 w-4 rounded-full bg-sky-400" aria-label="Blue"></button>
                                            <button type="button" x-on:click="selection.color = 'rose'" :class="selection.color === 'rose' ? 'ring-2 ring-white' : ''" class="h-4 w-4 rounded-full bg-rose-400" aria-label="Rose"></button>
                                        </div>
                                    </div>
                                    <input type="text"
                                           x-model="selection.note"
                                           x-on:keydown.enter.prevent="saveSelectionHighlight()"
                                           placeholder="Add a note (optional)…"
                                           class="mb-2 w-full rounded border border-zinc-700 bg-zinc-950 px-2 py-1 text-xs text-zinc-100 placeholder:text-zinc-500 focus:border-amber-400 focus:outline-none" />
                                    <div class="flex items-center justify-between">
                                        <button type="button" x-on:click="clearSelection()" class="text-[11px] text-zinc-400 hover:text-zinc-200">Cancel</button>
                                        <button type="button" x-on:click="saveSelectionHighlight()"
                                                class="rounded bg-amber-400 px-2.5 py-1 text-xs font-semibold text-zinc-950 hover:bg-amber-300">
                                            Save Highlight
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Page Canvas + Selectable Text Layer (Page View) --}}
                            <div wire:ignore
                                 x-show="!readMode"
                                 x-ref="pageWrap"
                                 x-on:mouseup="onTextSelect($event, 'canvas')"
                                 class="relative mx-auto shadow-2xl"
                                 :style="pageWidth ? `width:${pageWidth}px;height:${pageHeight}px;` : ''">
                                <canvas x-ref="canvas"
                                        class="block rounded-sm transition-[filter] duration-200"
                                        :style="theme === 'dark' ? 'filter: invert(0.90) hue-rotate(180deg) contrast(0.96);' : ''"></canvas>
                                <div x-ref="textLayer" class="pdf-text-layer"></div>

                                {{-- Floating Highlight Popover on Text Selection --}}
                                <div x-show="selection.open && !readMode"
                                     x-cloak
                                     x-transition.opacity.duration.120ms
                                     :style="`left:${selection.x}px;top:${selection.y}px;`"
                                     class="absolute z-40 w-64 -translate-x-1/2 -translate-y-full rounded-lg border border-zinc-700 bg-zinc-900/95 p-2.5 text-xs text-zinc-100 shadow-2xl backdrop-blur">
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400">Highlight p.<span x-text="page"></span></span>
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" x-on:click="selection.color = 'yellow'" :class="selection.color === 'yellow' ? 'ring-2 ring-white' : ''" class="h-4 w-4 rounded-full bg-amber-300" aria-label="Yellow"></button>
                                            <button type="button" x-on:click="selection.color = 'green'" :class="selection.color === 'green' ? 'ring-2 ring-white' : ''" class="h-4 w-4 rounded-full bg-emerald-400" aria-label="Green"></button>
                                            <button type="button" x-on:click="selection.color = 'blue'" :class="selection.color === 'blue' ? 'ring-2 ring-white' : ''" class="h-4 w-4 rounded-full bg-sky-400" aria-label="Blue"></button>
                                            <button type="button" x-on:click="selection.color = 'rose'" :class="selection.color === 'rose' ? 'ring-2 ring-white' : ''" class="h-4 w-4 rounded-full bg-rose-400" aria-label="Rose"></button>
                                        </div>
                                    </div>
                                    <input type="text"
                                           x-model="selection.note"
                                           x-on:keydown.enter.prevent="saveSelectionHighlight()"
                                           placeholder="Add a note (optional)…"
                                           class="mb-2 w-full rounded border border-zinc-700 bg-zinc-950 px-2 py-1 text-xs text-zinc-100 placeholder:text-zinc-500 focus:border-amber-400 focus:outline-none" />
                                    <div class="flex items-center justify-between">
                                        <button type="button" x-on:click="clearSelection()" class="text-[11px] text-zinc-400 hover:text-zinc-200">Cancel</button>
                                        <button type="button" x-on:click="saveSelectionHighlight()"
                                                class="rounded bg-amber-400 px-2.5 py-1 text-xs font-semibold text-zinc-950 hover:bg-amber-300">
                                            Save Highlight
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Bottom Prev / Next Page Bar --}}
                            <div class="mt-4 flex items-center gap-3 text-xs"
                                 :class="theme === 'dark' ? 'text-zinc-300' : 'text-zinc-700'">
                                <button type="button" x-on:click="prevPage()" :disabled="page <= 1"
                                        class="rounded border border-current/20 px-3 py-1 disabled:opacity-30">← Previous</button>
                                <span class="tabular-nums">Page <strong x-text="page">1</strong> <span x-show="numPages">of <span x-text="numPages"></span></span></span>
                                <button type="button" x-on:click="nextPage()" :disabled="numPages > 0 && page >= numPages"
                                        class="rounded border border-current/20 px-3 py-1 disabled:opacity-30">Next →</button>
                            </div>
                        </div>

                        {{-- Native PDF Fallback (with Dark/Light optical filter) --}}
                        <div x-show="fallbackIframe" x-cloak class="relative">
                            <button type="button" x-on:click="fallbackIframe = false"
                                    class="mb-2 text-xs text-link hover:underline">← Back to Highlightable Reader</button>
                            <iframe wire:key="pdf-frame-{{ $selected->id }}-{{ $initialPage }}-{{ $showPdfToolbar ? 1 : 0 }}"
                                    src="{{ $selected->pdfUrl() }}#page={{ $initialPage }}&toolbar={{ $showPdfToolbar ? 1 : 0 }}&navpanes=0&view=FitH"
                                    title="{{ $selected->title }} PDF"
                                    :style="theme === 'dark' ? 'filter: invert(0.90) hue-rotate(180deg);' : ''"
                                    :class="readMode ? 'h-[88vh]' : 'h-[84vh]'"
                                    class="w-full rounded-lg border border-zinc-800 bg-zinc-950"></iframe>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center gap-2.5 rounded-lg border border-dashed border-zinc-800 bg-sidebar/60 px-6 py-10 text-center">
                            <svg class="h-8 w-8 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <p class="text-sm text-muted">No PDF attached to this {{ $isArticles ? 'article' : 'book' }} yet.</p>
                            <button type="button"
                                    wire:click="openPdfUpload"
                                    class="rounded border border-zinc-700 bg-panel px-3 py-1.5 text-xs font-medium text-link transition hover:border-link/50">
                                Attach PDF
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Slide-Over Highlights Notebook Drawer --}}
                @if ($showHighlights)
                    <aside data-highlights-panel
                           :class="readMode ? 'h-full w-80 shrink-0 overflow-y-auto' : 'max-h-[84vh] w-full shrink-0 overflow-y-auto lg:w-80'"
                           class="rounded-lg border border-zinc-800 bg-sidebar p-3.5">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-muted">
                                Highlights ({{ $highlights->count() }})
                            </h3>
                            <button type="button" wire:click="$set('showHighlights', false)"
                                    class="text-xs text-muted hover:text-body">Close</button>
                        </div>
                        <ul class="space-y-2.5">
                            @forelse ($highlights as $highlight)
                                @php
                                    $borderAccent = match ($highlight->color) {
                                        'green' => 'border-l-emerald-400',
                                        'blue' => 'border-l-sky-400',
                                        'rose' => 'border-l-rose-400',
                                        default => 'border-l-amber-300',
                                    };
                                @endphp
                                <li wire:key="highlight-{{ $highlight->id }}"
                                    class="rounded border border-white/10 border-l-2 {{ $borderAccent }} bg-panel p-2.5 text-xs">
                                    <div class="mb-1 flex items-center justify-between gap-2">
                                        @if ($highlight->page_number)
                                            <button type="button"
                                                    wire:click="jumpToPage({{ $highlight->page_number }})"
                                                    class="font-mono text-[11px] font-semibold text-link hover:underline">
                                                p. {{ $highlight->page_number }}
                                            </button>
                                        @else
                                            <span class="text-[11px] text-faint">—</span>
                                        @endif
                                        <button type="button"
                                                wire:click="deleteHighlight({{ $highlight->id }})"
                                                class="text-[11px] text-muted hover:text-red-400">
                                            remove
                                        </button>
                                    </div>
                                    <blockquote class="font-serif text-sm leading-relaxed text-body">“{{ $highlight->quote }}”</blockquote>
                                    @if ($highlight->note)
                                        <p class="mt-1.5 border-t border-white/5 pt-1.5 text-[11px] text-muted">{{ $highlight->note }}</p>
                                    @endif
                                </li>
                            @empty
                                <li class="rounded border border-dashed border-white/15 px-3 py-6 text-center text-xs text-muted">
                                    No highlights yet. Drag-select any passage in the book to save a highlight.
                                </li>
                            @endforelse
                        </ul>
                    </aside>
                @endif
            </div>

            {{-- Everything below hides cleanly in Adobe Read Mode --}}
            <div x-show="!readMode">

            {{-- Secondary Metadata Strip --}}
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

                <div class="grid flex-1 grid-cols-2 gap-2 sm:grid-cols-3">
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
                    <div class="col-span-2 flex flex-col justify-center gap-1 px-1 py-1 sm:col-span-1">
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
                </div>
            </div>

            {{-- Sessions History --}}
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
            </div>
        </section>
    @endif

    <script>
        function pdfBookReader(cfg) {
            return {
                pdfUrl: cfg.pdfUrl || null,
                page: cfg.initialPage || 1,
                numPages: 0,
                scale: 1.35,
                pageWidth: 0,
                pageHeight: 0,
                loading: false,
                fallbackIframe: false,
                readMode: false,
                reflowBlocks: [],
                readFontSize: (() => {
                    try { return Number(localStorage.getItem('read-font-size')) || 20; } catch (e) { return 20; }
                })(),
                theme: (() => {
                    try { return localStorage.getItem('read-theme') || 'dark'; } catch (e) { return 'dark'; }
                })(),
                stopUrl: cfg.stopUrl,
                activeBookId: cfg.activeBookId,
                highlights: cfg.highlights || [],
                toast: '',
                selection: { open: false, text: '', color: 'yellow', note: '', x: 0, y: 0 },
                _pdfDoc: null,
                _pdfjs: null,
                _rendering: false,
                _pendingPage: null,

                init() {
                    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                    window.addEventListener('pagehide', () => {
                        if (!this.activeBookId || !navigator.sendBeacon) return;
                        const fd = new FormData();
                        fd.append('_token', csrf);
                        fd.append('book_id', String(this.activeBookId));
                        navigator.sendBeacon(this.stopUrl, fd);
                    });

                    if (this.pdfUrl) {
                        this.loadPdf();
                    }
                },

                toggleTheme() {
                    this.theme = this.theme === 'dark' ? 'light' : 'dark';
                    try { localStorage.setItem('read-theme', this.theme); } catch (e) {}
                },

                toggleReadMode() {
                    this.readMode = !this.readMode;
                    this.clearSelection();
                    if (!this.readMode && this._pdfDoc) {
                        this.$nextTick(() => this.renderPage(this.page));
                    }
                },

                largerFont() {
                    this.readFontSize = Math.min(30, this.readFontSize + 2);
                    try { localStorage.setItem('read-font-size', String(this.readFontSize)); } catch (e) {}
                },

                smallerFont() {
                    this.readFontSize = Math.max(14, this.readFontSize - 2);
                    try { localStorage.setItem('read-font-size', String(this.readFontSize)); } catch (e) {}
                },

                buildReflowBlocks(textContent, pageHeightPt) {
                    const rawLines = [];
                    const buckets = new Map();

                    for (const item of textContent.items) {
                        const str = (item.str || '');
                        if (!str.trim()) continue;
                        const x = item.transform[4];
                        const y = item.transform[5];
                        const size = Math.abs(item.transform[3]) || Math.abs(item.transform[0]) || 11;

                        // Skip page headers and footers
                        if (pageHeightPt > 200 && (y > pageHeightPt - 58 || y < 56)) {
                            continue;
                        }

                        const yKey = Math.round(y / 2) * 2;
                        if (!buckets.has(yKey)) {
                            buckets.set(yKey, { y, xMin: x, size, parts: [] });
                        }
                        const line = buckets.get(yKey);
                        line.xMin = Math.min(line.xMin, x);
                        line.size = Math.max(line.size, size);
                        line.parts.push({ x, str });
                    }

                    const sortedLines = [...buckets.values()].sort((a, b) => b.y - a.y);
                    if (sortedLines.length === 0) return [];

                    const sizes = sortedLines.map((l) => l.size).sort((a, b) => a - b);
                    const bodySize = sizes[Math.floor(sizes.length / 2)] || 11;
                    const leftMargins = sortedLines.map((l) => l.xMin).sort((a, b) => a - b);
                    const baseLeft = leftMargins[Math.floor(leftMargins.length * 0.25)] || 72;

                    for (const l of sortedLines) {
                        l.parts.sort((a, b) => a.x - b.x);
                        const text = l.parts.map((p) => p.str).join(' ').replace(/\s+/g, ' ').trim();
                        if (text) {
                            rawLines.push({ y: l.y, x: l.xMin, size: l.size, text });
                        }
                    }

                    const blocks = [];
                    let currentPara = '';
                    let prevY = null;

                    const flushPara = () => {
                        const cleaned = currentPara.trim();
                        if (cleaned) {
                            blocks.push({ type: 'paragraph', text: cleaned });
                        }
                        currentPara = '';
                    };

                    for (const line of rawLines) {
                        const isHeading = line.size >= bodySize * 1.18;
                        const yGap = prevY !== null ? (prevY - line.y) : 0;
                        const isIndented = line.x > baseLeft + 8;

                        if (isHeading) {
                            flushPara();
                            const last = blocks[blocks.length - 1];
                            if (last && last.type === 'heading' && yGap < line.size * 1.8) {
                                last.text += ' ' + line.text;
                            } else {
                                blocks.push({ type: 'heading', text: line.text });
                            }
                        } else {
                            if (currentPara && (yGap > bodySize * 1.65 || isIndented)) {
                                flushPara();
                            }
                            if (currentPara.endsWith('-')) {
                                currentPara = currentPara.slice(0, -1) + line.text;
                            } else {
                                currentPara = currentPara ? currentPara + ' ' + line.text : line.text;
                            }
                        }
                        prevY = line.y;
                    }
                    flushPara();

                    return blocks;
                },

                renderHighlightedHtml(text) {
                    const escapeHtml = (s) => s
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');

                    let html = escapeHtml(text);
                    const pageHighlights = (this.highlights || []).filter((h) => Number(h.page) === Number(this.page));
                    const markClasses = {
                        yellow: 'rounded-sm bg-amber-300/35 px-0.5 text-inherit',
                        green: 'rounded-sm bg-emerald-400/35 px-0.5 text-inherit',
                        blue: 'rounded-sm bg-sky-400/35 px-0.5 text-inherit',
                        rose: 'rounded-sm bg-rose-400/35 px-0.5 text-inherit',
                    };

                    for (const h of pageHighlights) {
                        if (!h.quote || h.quote.length < 3) continue;
                        const needle = escapeHtml(h.quote);
                        const cls = markClasses[h.color] || markClasses.yellow;
                        const escapedRegex = needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                        html = html.replace(new RegExp(escapedRegex, 'gi'), (m) => `<mark class="${cls}">${m}</mark>`);
                    }

                    return html;
                },

                async loadPdf() {
                    this.loading = true;
                    try {
                        const pdfjs = await import('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.8.69/pdf.min.mjs');
                        pdfjs.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.8.69/pdf.worker.min.mjs';
                        this._pdfjs = pdfjs;
                        this._pdfDoc = await pdfjs.getDocument(this.pdfUrl).promise;
                        this.numPages = this._pdfDoc.numPages;
                        this.page = Math.min(Math.max(1, this.page), this.numPages);
                        await this.renderPage(this.page);
                    } catch (e) {
                        this.fallbackIframe = true;
                    } finally {
                        this.loading = false;
                    }
                },

                async renderPage(num) {
                    if (!this._pdfDoc) return;
                    if (this._rendering) {
                        this._pendingPage = num;
                        return;
                    }
                    this._rendering = true;
                    this.clearSelection();
                    try {
                        const page = await this._pdfDoc.getPage(num);
                        const dpr = Math.min(window.devicePixelRatio || 1, 2);
                        const viewport = page.getViewport({ scale: this.scale });
                        const canvas = this.$refs.canvas;
                        if (!canvas) return;

                        this.pageWidth = Math.round(viewport.width);
                        this.pageHeight = Math.round(viewport.height);

                        canvas.width = Math.round(viewport.width * dpr);
                        canvas.height = Math.round(viewport.height * dpr);
                        canvas.style.width = this.pageWidth + 'px';
                        canvas.style.height = this.pageHeight + 'px';

                        const ctx = canvas.getContext('2d');
                        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                        await page.render({ canvasContext: ctx, viewport }).promise;

                        await this.renderTextLayer(page, viewport);
                    } finally {
                        this._rendering = false;
                        if (this._pendingPage !== null && this._pendingPage !== num) {
                            const next = this._pendingPage;
                            this._pendingPage = null;
                            this.renderPage(next);
                        }
                    }
                },

                async renderTextLayer(page, viewport) {
                    const container = this.$refs.textLayer;
                    if (!container || !this._pdfjs) return;
                    container.innerHTML = '';

                    const textContent = await page.getTextContent();
                    const baseViewport = page.getViewport({ scale: 1 });
                    this.reflowBlocks = this.buildReflowBlocks(textContent, baseViewport.height);
                    const pageHighlights = (this.highlights || []).filter((h) => Number(h.page) === Number(this.page));
                    const colorWash = {
                        yellow: 'rgba(250, 204, 21, 0.28)',
                        green: 'rgba(52, 211, 153, 0.28)',
                        blue: 'rgba(56, 189, 248, 0.28)',
                        rose: 'rgba(251, 113, 133, 0.28)',
                    };

                    for (const item of textContent.items) {
                        if (!item.str) continue;
                        const tx = this._pdfjs.Util.transform(viewport.transform, item.transform);
                        const fontHeight = Math.hypot(tx[2], tx[3]);
                        const span = document.createElement('span');
                        span.textContent = item.str;
                        span.style.left = tx[4] + 'px';
                        span.style.top = (tx[5] - fontHeight) + 'px';
                        span.style.fontSize = fontHeight + 'px';
                        span.style.fontFamily = 'serif';
                        if (item.width > 0) {
                            span.style.minWidth = (item.width * viewport.scale) + 'px';
                        }

                        const trimmed = item.str.trim().toLowerCase();
                        if (trimmed.length >= 3) {
                            const match = pageHighlights.find((h) => h.quote && h.quote.toLowerCase().includes(trimmed));
                            if (match) {
                                span.style.backgroundColor = colorWash[match.color] || colorWash.yellow;
                            }
                        }

                        container.appendChild(span);
                    }
                },

                goToPage(num) {
                    const target = parseInt(num, 10);
                    if (isNaN(target) || target < 1) return;
                    this.page = this.numPages ? Math.min(target, this.numPages) : target;
                    if (this._pdfDoc) {
                        this.renderPage(this.page);
                    }
                },

                prevPage() {
                    if (this.page <= 1) return;
                    this.page--;
                    this.$wire.set('readerCurrentPage', String(this.page), false);
                    this.$wire.saveCurrentPage();
                    this.renderPage(this.page);
                },

                nextPage() {
                    if (this.numPages && this.page >= this.numPages) return;
                    this.page++;
                    this.$wire.set('readerCurrentPage', String(this.page), false);
                    this.$wire.saveCurrentPage();
                    this.renderPage(this.page);
                },

                zoomIn() {
                    this.scale = Math.min(2.4, Math.round((this.scale + 0.15) * 100) / 100);
                    this.renderPage(this.page);
                },

                zoomOut() {
                    this.scale = Math.max(0.75, Math.round((this.scale - 0.15) * 100) / 100);
                    this.renderPage(this.page);
                },

                onTextSelect(e, mode = 'canvas') {
                    const sel = window.getSelection();
                    const text = sel ? sel.toString().replace(/\s+/g, ' ').trim() : '';
                    if (text.length < 2 || !sel.rangeCount) {
                        return;
                    }
                    const range = sel.getRangeAt(0);
                    const rect = range.getBoundingClientRect();
                    const wrapEl = mode === 'reflow' ? this.$refs.reflowWrap : this.$refs.pageWrap;
                    const wrapRect = wrapEl?.getBoundingClientRect();
                    if (!wrapRect || rect.width === 0) return;

                    this.selection.text = text;
                    this.selection.note = '';
                    this.selection.x = Math.min(Math.max(135, rect.left - wrapRect.left + rect.width / 2), wrapRect.width - 135);
                    this.selection.y = Math.max(80, rect.top - wrapRect.top - 8);
                    this.selection.open = true;
                },

                clearSelection() {
                    this.selection.open = false;
                    this.selection.text = '';
                    this.selection.note = '';
                    try { window.getSelection()?.removeAllRanges(); } catch (e) {}
                },

                async saveSelectionHighlight() {
                    if (!this.selection.text) return;
                    const quote = this.selection.text;
                    const color = this.selection.color;
                    const note = this.selection.note;
                    const pageNum = this.page;

                    this.clearSelection();
                    await this.$wire.addHighlight(quote, pageNum, color, note);
                    this.highlights.push({ page: pageNum, quote, color });
                    this.renderPage(this.page);

                    this.toast = 'Saved highlight (p. ' + pageNum + ')';
                    setTimeout(() => { this.toast = ''; }, 2400);
                },

                handleKey(e) {
                    const tag = document.activeElement?.tagName;
                    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return;

                    if (e.key === 'Escape') {
                        if (this.selection.open) {
                            this.clearSelection();
                        } else if (this.readMode) {
                            this.readMode = false;
                        } else if (this.$wire.readerOpen) {
                            this.$wire.closeReader();
                        }
                        return;
                    }

                    if (!this.pdfUrl) return;
                    if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        this.nextPage();
                    } else if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        this.prevPage();
                    } else if ((e.key === 'r' || e.key === 'R') && !e.metaKey && !e.ctrlKey) {
                        e.preventDefault();
                        this.toggleReadMode();
                    }
                },
            };
        }

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

        // Lazy-loads PDF.js from CDN only when a PDF is chosen, renders Page 1 to a JPEG thumbnail,
        // and syncs the data URL to Livewire so cover_path is saved automatically on submit.
        function pdfCoverExtractor(targetProp) {
            return {
                preview: null,
                status: '',
                async extract(event) {
                    const file = event.target.files && event.target.files[0];
                    this.preview = null;
                    this.status = '';
                    this.$wire.set(targetProp, null);

                    if (!file || (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf'))) {
                        return;
                    }

                    this.status = 'Extracting Page 1 cover…';

                    try {
                        const pdfjs = await import('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.8.69/pdf.min.mjs');
                        pdfjs.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.8.69/pdf.worker.min.mjs';

                        const buffer = await file.arrayBuffer();
                        const pdf = await pdfjs.getDocument({ data: buffer }).promise;
                        const page = await pdf.getPage(1);
                        const baseViewport = page.getViewport({ scale: 1 });
                        const scale = Math.min(2, 360 / baseViewport.width);
                        const viewport = page.getViewport({ scale });

                        const canvas = document.createElement('canvas');
                        canvas.width = Math.round(viewport.width);
                        canvas.height = Math.round(viewport.height);

                        const ctx = canvas.getContext('2d');
                        await page.render({ canvasContext: ctx, viewport }).promise;

                        const dataUrl = canvas.toDataURL('image/jpeg', 0.82);
                        this.preview = dataUrl;
                        this.status = 'Cover extracted from Page 1.';
                        this.$wire.set(targetProp, dataUrl);
                    } catch (e) {
                        this.status = 'PDF attached (will use typographic cover).';
                    }
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
</div>
