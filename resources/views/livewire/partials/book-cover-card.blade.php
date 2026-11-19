@php
    $isSelected = $selectedId === $book->id;
    $isFinished = $finished ?? false;
    $spineUrl = $book->spineUrl();
    $publisherName = trim((string) ($book->publisher ?? ''));
    $publisherLower = strtolower($publisherName);
    $isPenguinPublisher = $publisherLower !== '' && str_contains($publisherLower, 'penguin');

    // Clean publisher spine tail mark for non-Penguin publishers
    $publisherMark = match (true) {
        $publisherLower === '' => null,
        str_contains($publisherLower, 'faber') => 'ff',
        str_contains($publisherLower, 'modern library') => 'ML',
        str_contains($publisherLower, 'oxford') => 'OXFORD',
        str_contains($publisherLower, 'vintage') => 'VINTAGE',
        str_contains($publisherLower, 'hackett') => 'HACKETT',
        str_contains($publisherLower, "o'reilly") || str_contains($publisherLower, 'oreilly') => "O'REILLY",
        default => strtoupper(mb_substr($publisherName, 0, 12)),
    };

    // Dynamic physical spine thickness based on page count
    $pages = (int) ($book->total_pages ?? 0);
    $spineHeightClass = match (true) {
        $pages >= 700 => 'h-[88px] sm:h-[96px]',
        $pages >= 350 => 'h-[68px] sm:h-[74px]',
        $pages >= 200 => 'h-[56px] sm:h-[62px]',
        default => 'h-[48px] sm:h-[52px]',
    };

    // Authentic publisher book-spine palettes (Penguin Classics, Great Ideas, Vintage, Modern Classics)
    $editions = [
        // 0: Penguin Classics Jet Black
        [
            'bg' => '#141417',
            'title' => 'text-[#f6f3ec]',
            'author' => 'text-[#ff7a1a]',
            'band' => '#ff6b00',
            'rule' => 'bg-white/20',
            'colophonBg' => '#ff6b00',
            'colophonRing' => '#f6f3ec',
            'colophonBird' => '#141417',
            'font' => 'font-serif',
            'case' => '',
            'light' => false,
        ],
        // 1: Penguin Great Ideas Warm Ivory / Cream
        [
            'bg' => '#eee8da',
            'title' => 'text-[#171614]',
            'author' => 'text-[#6e3b28]',
            'band' => '#171614',
            'rule' => 'bg-black/20',
            'colophonBg' => '#171614',
            'colophonRing' => '#eee8da',
            'colophonBird' => '#171614',
            'font' => 'font-serif',
            'case' => 'uppercase tracking-[0.06em]',
            'light' => true,
        ],
        // 2: Iconic Penguin Orange
        [
            'bg' => '#e8611a',
            'title' => 'text-[#14110f]',
            'author' => 'text-[#fff7ed]',
            'band' => '#f7f3eb',
            'rule' => 'bg-black/20',
            'colophonBg' => '#14110f',
            'colophonRing' => '#f7f3eb',
            'colophonBird' => '#14110f',
            'font' => 'font-sans',
            'case' => 'uppercase tracking-[0.04em]',
            'light' => true,
        ],
        // 3: Mustard / Ochre Yellow Paperback
        [
            'bg' => '#e6b928',
            'title' => 'text-[#15130f]',
            'author' => 'text-[#3d3110]',
            'band' => '#15130f',
            'rule' => 'bg-black/20',
            'colophonBg' => '#15130f',
            'colophonRing' => '#f6ecd0',
            'colophonBird' => '#15130f',
            'font' => 'font-serif',
            'case' => '',
            'light' => true,
        ],
        // 4: Royal Cobalt Blue Cloth
        [
            'bg' => '#1b4a82',
            'title' => 'text-[#f7f5f0]',
            'author' => 'text-[#e8c674]',
            'band' => '#e8c674',
            'rule' => 'bg-white/25',
            'colophonBg' => '#e8c674',
            'colophonRing' => '#1b4a82',
            'colophonBird' => '#f7f5f0',
            'font' => 'font-serif',
            'case' => '',
            'light' => false,
        ],
        // 5: Deep Crimson Paperback
        [
            'bg' => '#8b1e24',
            'title' => 'text-[#faf5eb]',
            'author' => 'text-[#f2c078]',
            'band' => '#161112',
            'rule' => 'bg-white/20',
            'colophonBg' => '#f2c078',
            'colophonRing' => '#8b1e24',
            'colophonBird' => '#161112',
            'font' => 'font-serif',
            'case' => 'uppercase tracking-[0.05em]',
            'light' => false,
        ],
        // 6: Crisp White Modern Classics
        [
            'bg' => '#f4f2ec',
            'title' => 'text-[#14161a]',
            'author' => 'text-[#1d5b8c]',
            'band' => '#1d5b8c',
            'rule' => 'bg-black/15',
            'colophonBg' => '#ff6b00',
            'colophonRing' => '#f4f2ec',
            'colophonBird' => '#14161a',
            'font' => 'font-sans',
            'case' => '',
            'light' => true,
        ],
        // 7: Deep Forest Green
        [
            'bg' => '#185440',
            'title' => 'text-[#f5f1e6]',
            'author' => 'text-[#d9c082]',
            'band' => '#d9c082',
            'rule' => 'bg-white/20',
            'colophonBg' => '#d9c082',
            'colophonRing' => '#185440',
            'colophonBird' => '#f5f1e6',
            'font' => 'font-serif',
            'case' => '',
            'light' => false,
        ],
    ];

    // Match well-known philosophy titles to iconic Penguin editions, fallback deterministically by id
    $titleLower = strtolower($book->title);
    $editionIndex = match (true) {
        str_contains($titleLower, 'montaigne') || str_contains($titleLower, 'essays') => 0,
        str_contains($titleLower, 'meditations') => 1,
        str_contains($titleLower, 'stoic') || str_contains($titleLower, 'seneca') => 2,
        str_contains($titleLower, 'republic') => 3,
        str_contains($titleLower, 'ethics') => 4,
        str_contains($titleLower, 'zarathustra') => 5,
        str_contains($titleLower, 'sisyphus') => 6,
        default => $book->id % count($editions),
    };
    $ed = $editions[$editionIndex];
@endphp

<button type="button"
        wire:click="selectBook({{ $book->id }})"
        @if (! $spineUrl) style="background-color: {{ $ed['bg'] }};" @endif
        @class([
            'relative flex w-full items-center overflow-hidden rounded-[3px] text-left transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-link',
            $spineHeightClass,
            'translate-x-3 ring-2 ring-link shadow-[0_8px_22px_rgba(88,166,255,0.28)]' => $isSelected,
            'shadow-[0_3px_9px_rgba(0,0,0,0.78)] hover:translate-x-1.5 hover:brightness-105' => ! $isSelected,
            'opacity-70 hover:opacity-95' => $isFinished && ! $isSelected,
        ])>
    @if ($spineUrl)
        {{-- Full-bleed photorealistic physical book spine image --}}
        <img src="{{ $spineUrl }}"
             alt="{{ $book->title }} spine"
             loading="lazy"
             class="h-full w-full object-cover object-center" />
        <span class="pointer-events-none absolute inset-x-0 top-0 h-px bg-white/25"></span>
        <span class="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-black/60"></span>

        {{-- Screen-reader & status indicators without cluttering the photographic spine --}}
        <span class="sr-only">
            @include('livewire.partials.read-item-name', [
                'title' => $book->title,
                'startedToday' => $book->isStartedToday(),
                'finished' => $isFinished,
            ])
            @if ($book->author) — {{ $book->author }} @endif
        </span>
    @else
        {{-- 3D curved physical book-spine lighting (top specular highlight + bottom curve shadow) --}}
        <span class="pointer-events-none absolute inset-0"
              style="background: linear-gradient(180deg, rgba(255,255,255,0.24) 0%, rgba(255,255,255,0.06) 14%, rgba(0,0,0,0) 46%, rgba(0,0,0,0.18) 82%, rgba(0,0,0,0.38) 100%);"></span>

        {{-- Top & bottom paperboard edge bevels --}}
        <span class="pointer-events-none absolute inset-x-0 top-0 h-px bg-white/30"></span>
        <span class="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-black/50"></span>

        {{-- Left publisher head-band accent + realistic spine hinge crease --}}
        <span class="relative z-10 h-full w-2.5 shrink-0 sm:w-3.5" style="background-color: {{ $ed['band'] }};"></span>
        <span class="pointer-events-none relative z-10 h-full w-px shrink-0 bg-black/30"></span>
        <span class="pointer-events-none relative z-10 mr-3 h-full w-px shrink-0 bg-white/20 sm:mr-5"></span>

        {{-- Main Book Spine Face: Author + Title (laid out horizontally like a real stacked book) --}}
        <span class="relative z-10 flex min-w-0 flex-1 items-baseline gap-3 py-2 pr-4 sm:gap-5">
            @if ($book->author)
                <span @class([
                    'hidden shrink-0 text-[11px] font-semibold uppercase tracking-[0.18em] sm:inline sm:text-xs',
                    $ed['author'],
                ])>
                    {{ $book->author }}
                </span>
                <span @class(['hidden h-3 w-px shrink-0 sm:inline-block', $ed['rule']]) aria-hidden="true"></span>
            @endif

            <span @class([
                'min-w-0 flex-1 truncate text-lg font-bold leading-tight sm:text-2xl',
                $ed['font'],
                $ed['case'],
                $ed['title'],
            ])>
                @include('livewire.partials.read-item-name', [
                    'title' => $book->title,
                    'startedToday' => $book->isStartedToday(),
                    'finished' => $isFinished,
                    'truncate' => true,
                ])
            </span>

            {{-- Mobile author subtitle --}}
            @if ($book->author)
                <span @class(['shrink-0 text-[10px] font-semibold uppercase tracking-wider sm:hidden', $ed['author']])>
                    {{ $book->author }}
                </span>
            @endif
        </span>

        {{-- Right spine crease + Publisher-specific Colophon (Penguin only for Penguin, other publishers get their own mark) --}}
        @if ($isPenguinPublisher || $publisherMark !== null)
            <span class="pointer-events-none relative z-10 h-full w-px shrink-0 bg-black/25"></span>
            <span class="pointer-events-none relative z-10 h-full w-px shrink-0 bg-white/15"></span>
            <span class="relative z-10 flex shrink-0 items-center justify-center px-3.5 sm:px-5">
                @if ($isPenguinPublisher)
                    <span class="inline-flex h-7 w-5 items-center justify-center rounded-full p-[2px] shadow-sm"
                          style="background-color: {{ $ed['colophonBg'] }};"
                          title="{{ $publisherName }}"
                          aria-label="{{ $publisherName }}">
                        <span class="inline-flex h-full w-full items-center justify-center rounded-full"
                              style="background-color: {{ $ed['colophonRing'] }};">
                            <span class="h-3 w-2 rounded-full" style="background-color: {{ $ed['colophonBird'] }};"></span>
                        </span>
                    </span>
                @else
                    <span @class([
                        'rounded border px-1.5 py-0.5 text-[9px] font-bold tracking-[0.16em]',
                        'border-black/25 text-black/75' => $ed['light'],
                        'border-white/25 text-white/80' => ! $ed['light'],
                    ]) title="{{ $publisherName }}">
                        {{ $publisherMark }}
                    </span>
                @endif
            </span>
        @else
            <span class="pointer-events-none relative z-10 mr-3 h-full w-px shrink-0 bg-black/25 sm:mr-5"></span>
        @endif
    @endif

    @if ($book->current_page && $book->total_pages)
        @php
            $progressPct = min(100, max(2, (int) round(($book->current_page / $book->total_pages) * 100)));
        @endphp
        <span class="pointer-events-none absolute inset-x-0 bottom-0 z-20 h-[2px] bg-black/40 opacity-0 transition-opacity duration-200 group-hover:opacity-100" aria-hidden="true">
            <span class="block h-full bg-[#ff6b00]" style="width: {{ $progressPct }}%;"></span>
        </span>
    @endif
</button>
