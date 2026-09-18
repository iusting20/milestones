<div class="mb-3 flex items-center gap-1.5">
    <h2 class="text-xs font-bold uppercase tracking-widest text-muted">
        {{ ucfirst($hub) }}
    </h2>
    <button type="button"
            wire:click="startAddProject"
            class="text-xs leading-none text-muted/40 transition hover:text-link"
            aria-label="Add project">+</button>
</div>

@if ($addingProject)
    <form wire:submit="addProject" class="mb-3">
        <input type="text"
               wire:model="newProjectName"
               wire:keydown.escape="cancelAddProject"
               placeholder="Project name"
               autofocus
               class="w-full rounded border border-white/10 bg-panel px-2 py-1.5 text-sm focus:border-link focus:outline-none" />
    </form>
@endif

<ul class="space-y-0.5">
    @forelse ($projects as $project)
        <li wire:key="project-{{ $project->id }}">
            <button type="button"
                    wire:click="selectProject('{{ $project->slug }}')"
                    @class([
                        'flex w-full items-center gap-1 rounded px-2 py-1.5 text-left text-sm no-underline transition',
                        'bg-panel text-body font-semibold' => $selected?->id === $project->id,
                        'text-muted hover:bg-white/5 hover:text-body' => $selected?->id !== $project->id,
                        'opacity-50' => $project->is_archived,
                    ])>
                <span class="truncate">{{ $project->name }}</span>
                @if ($project->is_archived)
                    <span class="ml-auto text-[10px] uppercase text-muted">arch</span>
                @endif
            </button>
        </li>
    @empty
        <li class="px-2 py-2 text-xs text-muted">No projects</li>
    @endforelse
</ul>
