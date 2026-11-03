@extends('impact-maps.layout')

@section('content')
    <div x-data="{ create: {{ $errors->hasAny(['name', 'client', 'client_new']) ? 'true' : 'false' }}, tpl: false,
            pickTpl(t) { this.$refs.mapName.value = t; this.tpl = false; this.$nextTick(() => { this.$refs.mapName.focus(); this.$refs.mapName.select(); }); },
            closeTpl() { this.tpl = false; this.$nextTick(() => this.$refs.tplBtn.focus()); } }"
         x-init="$watch('create', v => v && $nextTick(() => $refs.mapName.focus()))"
         @keydown.escape.window="tpl ? closeTpl() : (create = false)">
        <h1>Impact Maps</h1>

        <div class="bar">
            <button type="button" class="toon-btn" @click="create = true">+ New map</button>
        </div>

        @php
            $templates = [
                ['📣', 'Increase awareness', '#7b86c6'],
                ['🧲', 'Increase acquisition rate', '#9fbf3b'],
                ['⚡', 'Increase activation rate', '#f9b11b'],
                ['💬', 'Increase engagement rate', '#ef7a7a'],
                ['🔁', 'Increase retention rate', '#7b86c6'],
                ['💰', 'Increase revenue', '#9fbf3b'],
                ['🤝', 'Increase referrals', '#fbd75b'],
            ];
        @endphp
        <div class="modal-bg tpl-bg" x-show="tpl" x-cloak x-transition.opacity @click.self="closeTpl()">
            <div class="modal tpl-modal" role="dialog" aria-modal="true" aria-labelledby="tpl-title">
                <h2 id="tpl-title">Goal templates</h2>
                <div class="tpl-grid">
                    @foreach ($templates as [$icon, $label, $color])
                        <button type="button" class="tpl-card" style="--c: {{ $color }}" @click="pickTpl(@js($label))">
                            <span class="tpl-ico" aria-hidden="true">{{ $icon }}</span>{{ $label }}
                        </button>
                    @endforeach
                </div>
                <div class="actions"><button type="button" class="toon-btn plain" @click="closeTpl()">Back</button></div>
            </div>
        </div>
        <div class="modal-bg" x-show="create" x-cloak x-transition.opacity @click.self="create = false">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="new-map-title">
                <h2 id="new-map-title">New impact map</h2>
                <form method="POST" action="{{ route('impact-maps.store') }}">
                    @csrf
                    <div class="name-row">
                        <label for="map-name">Goal / map name</label>
                        <button type="button" class="tpl-btn" x-ref="tplBtn" @click="tpl = true" aria-haspopup="dialog">view templates</button>
                    </div>
                    <input id="map-name" x-ref="mapName" type="text" name="name" maxlength="255" required value="{{ old('name') }}">
                    @error('name') <p class="err" style="margin: -8px 0 10px">{{ $message }}</p> @enderror
                    <label for="map-client">Client / project <span class="muted">(optional)</span></label>
                    @include('impact-maps._client-picker', ['id' => 'map-client', 'selected' => old('client', ''), 'newValue' => old('client_new')])
                    @error('client') <p class="err" style="margin: -8px 0 10px">{{ $message }}</p> @enderror
                    @error('client_new') <p class="err" style="margin: -8px 0 10px">{{ $message }}</p> @enderror
                    <div class="actions">
                        <button type="button" class="toon-btn plain" @click="create = false">Cancel</button>
                        <button type="submit" class="toon-btn">Create</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($maps->isEmpty())
            <p class="muted">No impact maps yet. Click + New map to create one.</p>
        @else
            <div id="im-groups">
            @foreach ($maps as $client => $group)
            <section class="grp" data-client="{{ $client }}">
            <h2 class="group" title="Drag to reorder">@if ($client !== '' && isset($clientSlugs[$client]))<a class="group-link" href="{{ route('impact-maps.client', ['secret' => $clientSlugs[$client]]) }}" title="Open shareable client page">{{ $client }}</a>@else{{ $client !== '' ? $client : 'No client / project' }}@endif</h2>
            <ul class="maps">
                @foreach ($group as $map)
                    <li data-slug="{{ $map->slug }}" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                        <a href="{{ route('impact-maps.show', ['map' => $map->slug]) }}">{{ $map->name }}</a>
                        <span class="meta">
                            <span class="muted">{{ $map->updated_at->timezone('Europe/Bucharest')->format('j M Y') }}</span>
                            <button type="button" class="wrench" title="Settings" aria-label="Map settings" :aria-expanded="open" @click="open = !open">
                                <svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true">
                                    <path d="M21.5 3.5c-3.6-.6-7 2.2-7 5.9 0 .8.1 1.5.4 2.2L4.6 21.9c-1.4 1.4-1.4 3.6 0 5 1.4 1.4 3.6 1.4 5 0l10.3-10.3c.7.3 1.4.4 2.2.4 3.7 0 6.5-3.4 5.9-7l-3.6 3.5-3.9-.9-.9-3.9z"
                                          fill="#f9b11b" stroke="#2b2b2b" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"/>
                                    <circle cx="7.4" cy="24.4" r="1.4" fill="#2b2b2b"/>
                                    <path d="M17.3 8.6c.3-1.5 1.5-2.6 3-2.9" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </span>
                        <div class="cog-menu" x-show="open" x-cloak x-transition>
                            <form method="POST" action="{{ route('impact-maps.update', ['map' => $map->slug]) }}">
                                @csrf
                                @method('PATCH')
                                @include('impact-maps._client-picker', ['id' => 'client-'.$map->slug, 'selected' => (string) $map->client, 'newValue' => ''])
                                <button type="submit" class="toon-btn">Save</button>
                            </form>
                            <form method="POST" action="{{ route('impact-maps.destroy', ['map' => $map->slug]) }}"
                                  onsubmit="return confirm('Delete this impact map and all its nodes?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="toon-btn plain" style="color: #b52b27">Delete map</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
            </section>
            @endforeach
            </div>
        @endif
    </div>
@push('scripts')
    @livewireScripts
    @vite(['resources/js/app.js'])
    <script type="module">
        const root = document.getElementById('im-groups');
        if (root && window.Sortable) {
            const save = (reload) => {
                document.body.classList.remove('im-dragging');
                const groups = [...root.querySelectorAll(':scope > .grp')].map((g) => ({
                    client: g.dataset.client,
                    maps: [...g.querySelectorAll('.maps > li[data-slug]')].map((li) => li.dataset.slug),
                }));
                fetch(@js(route('impact-maps.reorder')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                    body: JSON.stringify({ groups }),
                }).then((r) => { if (!r.ok) throw r; if (reload) location.reload(); })
                  .catch(() => { alert('Could not save the new order.'); location.reload(); });
            };
            const common = {
                animation: matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 200, easing: 'cubic-bezier(.2, .8, .2, 1)', ghostClass: 'im-ghost', chosenClass: 'im-chosen',
                onStart: () => document.body.classList.add('im-dragging'),
            };
            Sortable.create(root, { ...common, draggable: '.grp', handle: '.group', onEnd: (e) => e.oldIndex !== e.newIndex ? save(false) : document.body.classList.remove('im-dragging') });
            root.querySelectorAll('.maps').forEach((ul) => Sortable.create(ul, {
                ...common, group: 'im-maps', draggable: 'li[data-slug]', emptyInsertThreshold: 24,
                filter: '.wrench, .cog-menu', preventOnFilter: false,
                onEnd: (e) => (e.from !== e.to || e.oldIndex !== e.newIndex) ? save(e.from !== e.to) : document.body.classList.remove('im-dragging'),
            }));
        }
    </script>
@endpush
@endsection
