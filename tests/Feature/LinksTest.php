<?php

namespace Tests\Feature;

use App\Livewire\LinkHub;
use App\Models\Link;
use App\Models\LinkGroup;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_redirects_to_partners(): void
    {
        $this->get('/links')
            ->assertRedirect('/links/partners');
    }

    public function test_partners_hub_renders(): void
    {
        $this->get(route('links.hub', 'partners'))
            ->assertOk()
            ->assertSee('Partners')
            ->assertSeeLivewire(LinkHub::class);
    }

    public function test_partner_project_not_visible_on_assets(): void
    {
        $partner = Project::query()->create([
            'hub' => 'partners',
            'name' => 'Partner Co',
            'slug' => 'partner-co',
            'position' => 1,
        ]);

        Project::query()->create([
            'hub' => 'assets',
            'name' => 'Asset App',
            'slug' => 'asset-app',
            'position' => 1,
        ]);

        $this->get(route('links.hub', ['hub' => 'partners', 'slug' => $partner->slug]))
            ->assertOk()
            ->assertSee('Partner Co')
            ->assertDontSee('Asset App');

        $this->get(route('links.hub', 'assets'))
            ->assertOk()
            ->assertSee('Asset App')
            ->assertDontSee('Partner Co');

        $this->get(route('links.hub', ['hub' => 'assets', 'slug' => $partner->slug]))
            ->assertNotFound();
    }

    public function test_can_create_project_group_and_link(): void
    {
        Livewire::test(LinkHub::class, ['hub' => 'partners'])
            ->call('startAddProject')
            ->assertSet('addingProject', true)
            ->set('newProjectName', 'Neo Wars')
            ->call('addProject')
            ->assertRedirect(route('links.hub', ['hub' => 'partners', 'slug' => 'neo-wars']));

        $project = Project::query()->where('slug', 'neo-wars')->first();
        $this->assertNotNull($project);
        $this->assertSame('partners', $project->hub);

        Livewire::test(LinkHub::class, ['hub' => 'partners', 'slug' => 'neo-wars'])
            ->set('newGroupName', 'Apps')
            ->call('addGroup')
            ->assertSee('Apps');

        $group = LinkGroup::query()->where('name', 'Apps')->first();
        $this->assertNotNull($group);

        Livewire::test(LinkHub::class, ['hub' => 'partners', 'slug' => 'neo-wars'])
            ->call('startAddLink', $group->id)
            ->set('newLinkTitle', 'Staging')
            ->set('newLinkUrl', 'https://staging.example.com')
            ->set('newLinkNote', 'outdated')
            ->call('addLink');

        $this->assertDatabaseHas('links', [
            'title' => 'Staging',
            'url' => 'https://staging.example.com',
            'note' => 'outdated',
            'link_group_id' => $group->id,
        ]);
    }

    public function test_can_edit_and_delete_link(): void
    {
        $project = Project::query()->create([
            'hub' => 'assets',
            'name' => 'Radiologie',
            'slug' => 'radiologie',
            'position' => 1,
        ]);
        $group = LinkGroup::query()->create([
            'project_id' => $project->id,
            'name' => 'Docs',
            'position' => 1,
        ]);
        $link = Link::query()->create([
            'link_group_id' => $group->id,
            'title' => 'Wiki',
            'url' => 'https://wiki.example.com',
            'position' => 1,
        ]);

        Livewire::test(LinkHub::class, ['hub' => 'assets', 'slug' => 'radiologie'])
            ->call('startEditLink', $link->id)
            ->set('editLinkTitle', 'Docs Wiki')
            ->set('editLinkUrl', 'https://docs.example.com')
            ->set('editLinkNote', 'ask before remove')
            ->call('saveEditLink');

        $link->refresh();
        $this->assertSame('Docs Wiki', $link->title);
        $this->assertSame('https://docs.example.com', $link->url);
        $this->assertSame('ask before remove', $link->note);

        Livewire::test(LinkHub::class, ['hub' => 'assets', 'slug' => 'radiologie'])
            ->call('deleteLink', $link->id);

        $this->assertDatabaseMissing('links', ['id' => $link->id]);
    }

    public function test_edit_mode_moves_projects_and_groups_with_arrows(): void
    {
        $first = Project::query()->create([
            'hub' => 'partners',
            'name' => 'Alpha',
            'slug' => 'alpha',
            'position' => 1,
        ]);
        $second = Project::query()->create([
            'hub' => 'partners',
            'name' => 'Beta',
            'slug' => 'beta',
            'position' => 2,
        ]);
        $docs = LinkGroup::query()->create([
            'project_id' => $first->id,
            'name' => 'Docs',
            'position' => 1,
        ]);
        $apps = LinkGroup::query()->create([
            'project_id' => $first->id,
            'name' => 'Apps',
            'position' => 2,
        ]);

        Livewire::test(LinkHub::class, ['hub' => 'partners', 'slug' => 'alpha'])
            ->call('moveProject', $second->id, 'up')
            ->call('moveGroup', $apps->id, 'up');

        $this->assertSame(1, $second->fresh()->position);
        $this->assertSame(2, $first->fresh()->position);
        $this->assertSame(1, $apps->fresh()->position);
        $this->assertSame(2, $docs->fresh()->position);
    }

    public function test_archived_project_does_not_swap_with_active_neighbor(): void
    {
        $active = Project::query()->create([
            'hub' => 'partners',
            'name' => 'Active',
            'slug' => 'active',
            'position' => 1,
        ]);
        $archived = Project::query()->create([
            'hub' => 'partners',
            'name' => 'Archived',
            'slug' => 'archived',
            'position' => 2,
            'is_archived' => true,
        ]);

        Livewire::test(LinkHub::class, ['hub' => 'partners', 'slug' => 'active'])
            ->call('moveProject', $archived->id, 'up');

        $this->assertSame(1, $active->fresh()->position);
        $this->assertSame(2, $archived->fresh()->position);
    }
}
