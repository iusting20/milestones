@php
    $startedToday = $startedToday ?? false;
    $finished = $finished ?? false;
@endphp
<span class="inline-flex min-w-0 items-baseline gap-1">
    @if ($startedToday)
        <span class="shrink-0 font-normal text-mark" title="Started today" aria-label="Started today">⏻</span>
    @endif
    @if ($finished)
        <span class="shrink-0 font-normal text-check" title="Finished" aria-label="Finished">✓</span>
    @endif
    <span @class(['min-w-0', 'truncate' => $truncate ?? false])>{{ $title }}</span>
</span>
