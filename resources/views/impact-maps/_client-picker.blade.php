@php
    $new = \App\Http\Controllers\ImpactMapController::NEW_CLIENT;
    $selected = (string) ($selected ?? '');
    if ($selected !== '' && $selected !== $new && ! $clients->contains($selected)) { $clients = $clients->push($selected)->sort()->values(); }
@endphp
<div class="picker" x-data="{ pick: @js($selected) }">
    <select name="client" id="{{ $id }}" x-model="pick" class="toon-select" aria-label="Client / project">
        <option value="">— None —</option>
        @foreach ($clients as $c)
            <option value="{{ $c }}" @selected($selected === $c)>{{ $c }}</option>
        @endforeach
        <option value="{{ $new }}" @selected($selected === $new)>+ New client…</option>
    </select>
    <input type="text" name="client_new" maxlength="255" placeholder="New client / project name" aria-label="New client / project"
           value="{{ $newValue ?? '' }}" x-show="pick === @js($new)" x-cloak :disabled="pick !== @js($new)"
           x-effect="pick === @js($new) && $nextTick(() => $el.focus())">
</div>
