<div class="flex min-h-[calc(100vh-3.5rem)] flex-col md:flex-row">
    {{-- Mobile project picker --}}
    <div class="border-b border-white/10 bg-sidebar p-3 md:hidden">
        <button type="button"
                wire:click="$toggle('mobileSidebarOpen')"
                class="flex w-full items-center justify-between rounded border border-white/10 bg-panel px-3 py-2 text-left text-sm">
            <span>{{ $selected?->name ?? 'Select a project' }}</span>
            <span class="text-muted">{{ $mobileSidebarOpen ? '▲' : '▼' }}</span>
        </button>

        @if ($mobileSidebarOpen)
            <div class="mt-2 space-y-1 rounded border border-white/10 bg-panel p-2">
                @include('livewire.partials.project-list', ['compact' => true])
            </div>
        @endif
    </div>

    {{-- Desktop sidebar --}}
    <aside class="hidden w-64 shrink-0 border-r border-white/10 bg-sidebar p-4 md:block lg:w-72">
        @include('livewire.partials.project-list', ['compact' => false])
    </aside>

    {{-- Main content --}}
    <div class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8">
        @if (! $selected)
            <div class="text-muted">
                <p class="mb-4">No projects yet.</p>
                <mark>... TO ADD ...</mark>
                <p class="mt-4 text-sm">Create a project in the sidebar to get started.</p>
            </div>
        @else
            <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                <h1 class="text-2xl font-bold text-body sm:text-3xl">{{ $selected->name }}</h1>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    @if ($editMode)
                        <button type="button" wire:click="startRenameProject({{ $selected->id }})"
                                class="rounded border border-white/10 px-2 py-1 text-xs text-muted hover:text-body">Rename</button>
                        <button type="button" wire:click="toggleArchive({{ $selected->id }})"
                                class="rounded border border-white/10 px-2 py-1 text-xs text-muted hover:text-body">
                            {{ $selected->is_archived ? 'Unarchive' : 'Archive' }}
                        </button>
                        <button type="button"
                                wire:click="deleteProject({{ $selected->id }})"
                                wire:confirm="Delete this project and all its links?"
                                class="rounded border border-red-500/30 px-2 py-1 text-xs text-red-400 hover:bg-red-500/10">Delete</button>
                    @endif
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

            @if ($renamingProjectId === $selected->id)
                <form wire:submit="saveRenameProject" class="mb-6 flex flex-wrap gap-2">
                    <input type="text" wire:model="renameProjectName"
                           class="rounded border border-white/10 bg-sidebar px-3 py-2 text-sm focus:border-link focus:outline-none" />
                    <button type="submit" class="rounded bg-check px-3 py-2 text-sm text-white">Save</button>
                    <button type="button" wire:click="cancelForms" class="rounded border border-white/10 px-3 py-2 text-sm text-muted">Cancel</button>
                </form>
            @endif

            @forelse ($selected->linkGroups as $group)
                <section class="mb-8 border-b border-dashed border-white/15 pb-8 last:border-0" id="group-{{ $group->id }}">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        @if ($renamingGroupId === $group->id)
                            <form wire:submit="saveRenameGroup" class="flex flex-wrap gap-2">
                                <input type="text" wire:model="renameGroupName"
                                       class="rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none" />
                                <button type="submit" class="rounded bg-check px-2 py-1 text-xs text-white">Save</button>
                                <button type="button" wire:click="cancelForms" class="text-xs text-muted">Cancel</button>
                            </form>
                        @else
                            <h3 class="text-lg font-bold text-body">{{ $group->name }}</h3>
                            @if ($editMode)
                                <span class="flex flex-col">
                                    <button type="button"
                                            wire:click="moveGroup({{ $group->id }}, 'up')"
                                            @disabled($loop->first)
                                            class="px-0.5 text-[9px] leading-none text-muted hover:text-body disabled:pointer-events-none disabled:opacity-20"
                                            aria-label="Move {{ $group->name }} up">▲</button>
                                    <button type="button"
                                            wire:click="moveGroup({{ $group->id }}, 'down')"
                                            @disabled($loop->last)
                                            class="px-0.5 text-[9px] leading-none text-muted hover:text-body disabled:pointer-events-none disabled:opacity-20"
                                            aria-label="Move {{ $group->name }} down">▼</button>
                                </span>
                                <button type="button" wire:click="startRenameGroup({{ $group->id }})"
                                        class="text-xs text-muted hover:text-body">rename</button>
                                <button type="button"
                                        wire:click="deleteGroup({{ $group->id }})"
                                        wire:confirm="Delete this group and its links?"
                                        class="text-xs text-red-400/80 hover:text-red-400">delete</button>
                            @endif
                        @endif
                    </div>

                    <ul class="space-y-2">
                        @forelse ($group->links as $link)
                            <li class="group/link flex flex-wrap items-baseline gap-x-2 gap-y-1" wire:key="link-{{ $link->id }}">
                                @if ($editingLinkId === $link->id)
                                    <form wire:submit="saveEditLink" class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
                                        <input type="text" wire:model="editLinkTitle" placeholder="Title"
                                               class="rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none sm:w-40" />
                                        <input type="url" wire:model="editLinkUrl" placeholder="URL"
                                               class="rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none sm:flex-1" />
                                        <input type="text" wire:model="editLinkNote" placeholder="Note (optional)"
                                               class="rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none sm:w-40" />
                                        <button type="submit" class="rounded bg-check px-3 py-1.5 text-sm text-white">Save</button>
                                        <button type="button" wire:click="cancelForms" class="text-sm text-muted">Cancel</button>
                                    </form>
                                @else
                                    <span class="text-link" aria-hidden="true">◆</span>
                                    <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                                       class="font-medium text-link no-underline hover:underline">{{ $link->title }}</a>
                                    @if ($link->note)
                                        <span class="text-xs text-muted">{{ $link->note }}</span>
                                    @endif
                                    @if ($editMode)
                                        <button type="button" wire:click="startEditLink({{ $link->id }})"
                                                class="text-xs text-muted hover:text-body">edit</button>
                                        <button type="button"
                                                wire:click="deleteLink({{ $link->id }})"
                                                wire:confirm="Delete this link?"
                                                class="text-xs text-red-400/70 hover:text-red-400">delete</button>
                                    @endif
                                @endif
                            </li>
                        @empty
                            <li class="text-sm text-muted"><mark>... TO ADD ...</mark></li>
                        @endforelse
                    </ul>

                    @if ($editMode)
                        @if ($addingLinkToGroupId === $group->id)
                            <form wire:submit="addLink" class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                <input type="text" wire:model="newLinkTitle" placeholder="Title" required
                                       class="rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none sm:w-40" />
                                <input type="url" wire:model="newLinkUrl" placeholder="URL" required
                                       class="rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none sm:flex-1" />
                                <input type="text" wire:model="newLinkNote" placeholder="Note (optional)"
                                       class="rounded border border-white/10 bg-sidebar px-3 py-1.5 text-sm focus:border-link focus:outline-none sm:w-40" />
                                <button type="submit" class="rounded bg-check px-3 py-1.5 text-sm text-white">Add link</button>
                                <button type="button" wire:click="cancelForms" class="text-sm text-muted">Cancel</button>
                            </form>
                        @else
                            <button type="button" wire:click="startAddLink({{ $group->id }})"
                                    class="mt-3 text-sm text-link no-underline hover:underline">+ Add link</button>
                        @endif
                    @endif
                </section>
            @empty
                <p class="mb-4 text-sm text-muted"><mark>... TO ADD groups ...</mark></p>
            @endforelse

            @if ($editMode)
                <form wire:submit="addGroup" class="mt-2 flex max-w-md flex-col gap-2 sm:flex-row">
                    <input type="text" wire:model="newGroupName" placeholder="New group (e.g. Apps, Docs)"
                           class="flex-1 rounded border border-white/10 bg-sidebar px-3 py-2 text-sm focus:border-link focus:outline-none" />
                    <button type="submit" class="rounded border border-white/15 bg-panel px-4 py-2 text-sm font-medium hover:border-link/40">
                        Add group
                    </button>
                </form>
            @endif
        @endif
    </div>
</div>
