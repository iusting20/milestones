<?php

namespace App\Livewire;

use App\Models\Link;
use App\Models\LinkGroup;
use App\Models\Project;
use Livewire\Component;

class LinkHub extends Component
{
    public string $hub = 'partners';

    public ?string $selectedSlug = null;

    public string $newProjectName = '';

    public bool $addingProject = false;

    public string $newGroupName = '';

    public string $newLinkTitle = '';

    public string $newLinkUrl = '';

    public string $newLinkNote = '';

    public ?int $addingLinkToGroupId = null;

    public ?int $editingLinkId = null;

    public string $editLinkTitle = '';

    public string $editLinkUrl = '';

    public string $editLinkNote = '';

    public ?int $renamingProjectId = null;

    public string $renameProjectName = '';

    public ?int $renamingGroupId = null;

    public string $renameGroupName = '';

    public bool $mobileSidebarOpen = false;

    public bool $editMode = false;

    public function mount(string $hub, ?string $slug = null): void
    {
        abort_unless(in_array($hub, ['partners', 'assets'], true), 404);

        $this->hub = $hub;
        $this->selectedSlug = $slug;

        if ($this->selectedSlug === null) {
            $first = $this->projectsQuery()->first();
            if ($first) {
                $this->selectedSlug = $first->slug;
            }
        } else {
            $exists = $this->projectsQuery()->where('slug', $this->selectedSlug)->exists();
            if (! $exists) {
                abort(404);
            }
        }
    }

    public function selectProject(string $slug): void
    {
        $this->redirect(route('links.hub', ['hub' => $this->hub, 'slug' => $slug]), navigate: true);
    }

    public function startAddProject(): void
    {
        $this->resetLinkForms();
        $this->renamingProjectId = null;
        $this->renameProjectName = '';
        $this->renamingGroupId = null;
        $this->renameGroupName = '';
        $this->addingProject = true;
        $this->newProjectName = '';
    }

    public function cancelAddProject(): void
    {
        $this->addingProject = false;
        $this->newProjectName = '';
    }

    public function addProject(): void
    {
        $name = trim($this->newProjectName);
        if ($name === '') {
            return;
        }

        $max = (int) Project::query()->where('hub', $this->hub)->max('position');
        $slug = Project::uniqueSlug($this->hub, $name);

        Project::query()->create([
            'hub' => $this->hub,
            'name' => $name,
            'slug' => $slug,
            'position' => $max + 1,
            'is_archived' => false,
        ]);

        $this->addingProject = false;
        $this->newProjectName = '';
        $this->redirect(route('links.hub', ['hub' => $this->hub, 'slug' => $slug]), navigate: true);
    }

    public function startRenameProject(int $id): void
    {
        $project = $this->findProject($id);
        $this->renamingProjectId = $project->id;
        $this->renameProjectName = $project->name;
    }

    public function saveRenameProject(): void
    {
        if (! $this->renamingProjectId) {
            return;
        }

        $project = $this->findProject($this->renamingProjectId);
        $name = trim($this->renameProjectName);

        if ($name === '') {
            return;
        }

        $oldSlug = $project->slug;
        $slug = Project::uniqueSlug($this->hub, $name, $project->id);
        $project->update([
            'name' => $name,
            'slug' => $slug,
        ]);

        if ($this->selectedSlug === $oldSlug) {
            $this->renamingProjectId = null;
            $this->renameProjectName = '';
            $this->redirect(route('links.hub', ['hub' => $this->hub, 'slug' => $slug]), navigate: true);

            return;
        }

        $this->renamingProjectId = null;
        $this->renameProjectName = '';
    }

    public function toggleArchive(int $id): void
    {
        $project = $this->findProject($id);
        $project->update(['is_archived' => ! $project->is_archived]);
    }

    public function deleteProject(int $id): void
    {
        $project = $this->findProject($id);
        $wasSelected = $this->selectedSlug === $project->slug;
        $project->delete();

        if ($wasSelected) {
            $next = $this->projectsQuery()->value('slug');
            $this->redirect(
                route('links.hub', array_filter(['hub' => $this->hub, 'slug' => $next])),
                navigate: true
            );
        }
    }

    public function addGroup(): void
    {
        $project = $this->selectedProject();
        if (! $project) {
            return;
        }

        $name = trim($this->newGroupName);
        if ($name === '') {
            return;
        }

        $max = (int) $project->linkGroups()->max('position');

        LinkGroup::query()->create([
            'project_id' => $project->id,
            'name' => $name,
            'position' => $max + 1,
        ]);

        $this->newGroupName = '';
    }

    public function startRenameGroup(int $id): void
    {
        $group = $this->findGroup($id);
        $this->renamingGroupId = $group->id;
        $this->renameGroupName = $group->name;
    }

    public function saveRenameGroup(): void
    {
        if (! $this->renamingGroupId) {
            return;
        }

        $group = $this->findGroup($this->renamingGroupId);
        $name = trim($this->renameGroupName);

        if ($name === '') {
            return;
        }

        $group->update(['name' => $name]);
        $this->renamingGroupId = null;
        $this->renameGroupName = '';
    }

    public function deleteGroup(int $id): void
    {
        $this->findGroup($id)->delete();
    }

    public function startAddLink(int $groupId): void
    {
        $this->findGroup($groupId);
        $this->addingLinkToGroupId = $groupId;
        $this->newLinkTitle = '';
        $this->newLinkUrl = '';
        $this->newLinkNote = '';
        $this->editingLinkId = null;
    }

    public function addLink(): void
    {
        if (! $this->addingLinkToGroupId) {
            return;
        }

        $group = $this->findGroup($this->addingLinkToGroupId);
        $title = trim($this->newLinkTitle);
        $url = trim($this->newLinkUrl);

        if ($title === '' || $url === '') {
            return;
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://'.$url;
        }

        $max = (int) $group->links()->max('position');

        Link::query()->create([
            'link_group_id' => $group->id,
            'title' => $title,
            'url' => $url,
            'note' => trim($this->newLinkNote) !== '' ? trim($this->newLinkNote) : null,
            'position' => $max + 1,
        ]);

        $this->resetLinkForms();
    }

    public function startEditLink(int $id): void
    {
        $link = $this->findLink($id);
        $this->editingLinkId = $link->id;
        $this->editLinkTitle = $link->title;
        $this->editLinkUrl = $link->url;
        $this->editLinkNote = $link->note ?? '';
        $this->addingLinkToGroupId = null;
    }

    public function saveEditLink(): void
    {
        if (! $this->editingLinkId) {
            return;
        }

        $link = $this->findLink($this->editingLinkId);
        $title = trim($this->editLinkTitle);
        $url = trim($this->editLinkUrl);

        if ($title === '' || $url === '') {
            return;
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://'.$url;
        }

        $link->update([
            'title' => $title,
            'url' => $url,
            'note' => trim($this->editLinkNote) !== '' ? trim($this->editLinkNote) : null,
        ]);

        $this->resetLinkForms();
    }

    public function deleteLink(int $id): void
    {
        $this->findLink($id)->delete();
        $this->resetLinkForms();
    }

    public function cancelForms(): void
    {
        $this->resetLinkForms();
        $this->renamingProjectId = null;
        $this->renameProjectName = '';
        $this->renamingGroupId = null;
        $this->renameGroupName = '';
        $this->addingProject = false;
        $this->newProjectName = '';
    }

    public function render()
    {
        $projects = $this->projectsQuery()->get();
        $selected = $projects->firstWhere('slug', $this->selectedSlug);

        if ($selected) {
            $selected->load(['linkGroups.links']);
        }

        return view('livewire.link-hub', [
            'projects' => $projects,
            'selected' => $selected,
        ])->layout('layouts.app', ['hub' => $this->hub]);
    }

    private function projectsQuery()
    {
        return Project::query()
            ->where('hub', $this->hub)
            ->orderBy('is_archived')
            ->orderBy('position')
            ->orderBy('id');
    }

    private function selectedProject(): ?Project
    {
        if (! $this->selectedSlug) {
            return null;
        }

        return Project::query()
            ->where('hub', $this->hub)
            ->where('slug', $this->selectedSlug)
            ->first();
    }

    private function findProject(int $id): Project
    {
        return Project::query()
            ->where('hub', $this->hub)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function findGroup(int $id): LinkGroup
    {
        return LinkGroup::query()
            ->whereKey($id)
            ->whereHas('project', fn ($q) => $q->where('hub', $this->hub))
            ->firstOrFail();
    }

    private function findLink(int $id): Link
    {
        return Link::query()
            ->whereKey($id)
            ->whereHas('linkGroup.project', fn ($q) => $q->where('hub', $this->hub))
            ->firstOrFail();
    }

    private function resetLinkForms(): void
    {
        $this->addingLinkToGroupId = null;
        $this->newLinkTitle = '';
        $this->newLinkUrl = '';
        $this->newLinkNote = '';
        $this->editingLinkId = null;
        $this->editLinkTitle = '';
        $this->editLinkUrl = '';
        $this->editLinkNote = '';
    }
}
