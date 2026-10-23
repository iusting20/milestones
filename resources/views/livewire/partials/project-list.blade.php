<div class="mb-3 flex items-center gap-1.5">
    <h2 class="text-xs font-bold uppercase tracking-widest text-muted">
        {{ ucfirst($hub) }}
    </h2>
    <button type="button"
            wire:click="startAddProject"
            class="text-xs leading-none text-muted/40 transition hover:text-link"
            aria-label="Add project">+</button>
    @if ($editMode)
        <button type="button"
                wire:click="addDelimiter"
                class="text-[10px] leading-none tracking-widest text-muted/40 transition hover:text-link"
                aria-label="Add delimiter">---</button>
    @endif
</div>

@if ($addingProject)
    <form wire:submit="addProject" class="mb-2">
        <input type="text"
               wire:model="newProjectName"
               wire:keydown.escape="cancelAddProject"
               placeholder="Project name"
               autofocus
               class="w-full rounded border border-white/10 bg-panel px-2 py-1.5 text-sm focus:border-link focus:outline-none" />
    </form>
@endif

@php
    $activeProjects = $projects->where('is_archived', false)->values();
    $archivedProjects = $projects->where('is_archived', true)->values();
@endphp

@if ($projects->isEmpty())
    <p class="px-2 py-2 text-xs text-muted">No projects</p>
@endif

@if ($activeProjects->isNotEmpty())
    <ul class="space-y-0.5"
        @if ($editMode) x-data="linkSortList('reorderProjects')" x-on:dragover.prevent="over($event)" x-on:drop.prevent="drop($event)" @endif>
        @foreach ($activeProjects as $project)
            <li class="flex items-center gap-0.5" wire:key="project-{{ $project->id }}" data-sort-item="{{ $project->id }}">
                @if ($project->is_delimiter)
                    @if ($editMode)
                        <button type="button" draggable="true" x-on:dragstart.stop="start($event)"
                                class="shrink-0 text-muted hover:text-body" style="cursor:grab"
                                aria-label="Drag delimiter">
                            @include('livewire.partials.drag-handle')
                        </button>
                    @endif
                    <div class="project-delimiter" data-delimiter role="separator" style="flex:1 1 auto;min-width:0;height:0;margin:0.4rem 0.5rem;border-top:1px solid rgba(255,255,255,0.22)"></div>
                    @if ($editMode)
                        <button type="button"
                                wire:click="deleteProject({{ $project->id }})"
                                wire:confirm="Remove this delimiter?"
                                class="shrink-0 px-1 text-[10px] leading-none transition hover:text-red-400"
                                style="color:rgba(139,139,139,0.45)"
                                aria-label="Remove delimiter">x</button>
                    @endif
                @else
                    @if ($editMode)
                        <button type="button" draggable="true" x-on:dragstart.stop="start($event)"
                                class="shrink-0 text-muted hover:text-body" style="cursor:grab"
                                aria-label="Drag {{ $project->name }}">
                            @include('livewire.partials.drag-handle')
                        </button>
                    @endif
                    <button type="button"
                            wire:click="selectProject('{{ $project->slug }}')"
                            @class([
                                'flex min-w-0 flex-1 items-center gap-1 rounded px-2 py-1.5 text-left text-sm no-underline transition',
                                'bg-panel text-body font-semibold' => $selected?->id === $project->id,
                                'text-muted hover:bg-white/5 hover:text-body' => $selected?->id !== $project->id,
                                'opacity-50' => $project->is_archived,
                            ])>
                        <span class="truncate">{{ $project->name }}</span>
                        @if ($project->is_archived)
                            <span class="ml-auto text-[10px] uppercase text-muted">arch</span>
                        @endif
                    </button>
                @endif
            </li>
        @endforeach
    </ul>
@endif

@if ($archivedProjects->isNotEmpty())
    <div class="mt-3" x-data="{ open: {{ $selected?->is_archived ? 'true' : 'false' }} }">
        <button type="button"
                x-on:click="open = !open"
                class="flex w-full items-center gap-1.5 px-2 py-1 text-left text-[10px] font-bold uppercase tracking-widest text-muted hover:text-body"
                :aria-expanded="open.toString()">
            <span x-cloak x-show="open" class="text-[9px] leading-none">▼</span>
            <span :hidden="open" aria-hidden="true" style="display:inline-block;width:0;height:0;border-top:3.5px solid transparent;border-bottom:3.5px solid transparent;border-left:5px solid currentColor;vertical-align:-1px"></span>
            Archived
            <span class="font-normal tracking-normal">{{ $archivedProjects->count() }}</span>
        </button>
        <ul class="mt-0.5 space-y-0.5" x-show="open" x-cloak
            @if ($editMode) x-data="linkSortList('reorderProjects')" x-on:dragover.prevent="over($event)" x-on:drop.prevent="drop($event)" @endif>
            @foreach ($archivedProjects as $project)
                <li class="flex items-center gap-0.5" wire:key="project-{{ $project->id }}" data-sort-item="{{ $project->id }}">
                    @if ($editMode)
                        <button type="button" draggable="true" x-on:dragstart.stop="start($event)"
                                class="shrink-0 text-muted hover:text-body" style="cursor:grab"
                                aria-label="Drag {{ $project->name }}">
                            @include('livewire.partials.drag-handle')
                        </button>
                    @endif
                    <button type="button"
                            wire:click="selectProject('{{ $project->slug }}')"
                            @class([
                                'flex min-w-0 flex-1 items-center gap-1 rounded px-2 py-1.5 text-left text-sm no-underline transition',
                                'bg-panel text-body font-semibold' => $selected?->id === $project->id,
                                'text-muted hover:bg-white/5 hover:text-body' => $selected?->id !== $project->id,
                                'opacity-50' => $project->is_archived,
                            ])>
                        <span class="truncate">{{ $project->name }}</span>
                        <span class="ml-auto text-[10px] uppercase text-muted">arch</span>
                    </button>
                </li>
            @endforeach
        </ul>
    </div>
@endif
