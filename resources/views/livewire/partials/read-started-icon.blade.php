@php
    $startedLabel = $startedLabel ?? 'Started today';
@endphp
<span data-read-icon="started-today" class="inline-flex shrink-0 text-mark" title="{{ $startedLabel }}" aria-label="{{ $startedLabel }}">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" width="1em" height="1em" style="width:1em;height:1em;display:block;overflow:visible" aria-hidden="true" focusable="false">
        <path d="M12 3v9"/>
        <path d="M5.636 5.636a9 9 0 1 0 12.728 0"/>
    </svg>
</span>
