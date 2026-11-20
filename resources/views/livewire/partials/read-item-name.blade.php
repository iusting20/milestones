@php
    $startedToday = $startedToday ?? false;
    $startedLabel = $startedLabel ?? 'Started today';
    $finished = $finished ?? false;
    $truncate = $truncate ?? false;
@endphp
<span class="inline-flex min-w-0 max-w-full items-center gap-1">
    @if ($startedToday && ! $finished)
        @include('livewire.partials.read-started-icon', ['startedLabel' => $startedLabel])
    @endif
    @if ($finished)
        <span data-read-icon="finished" class="inline-flex shrink-0 text-check" title="Finished" aria-label="Finished">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="1em" height="1em" style="width:1em;height:1em;display:block;overflow:visible" aria-hidden="true" focusable="false">
                <path d="M5 12.5 9.5 17 19 7"/>
            </svg>
        </span>
    @endif
    <span @class(['min-w-0', 'truncate' => $truncate])>{{ $title }}</span>
</span>
