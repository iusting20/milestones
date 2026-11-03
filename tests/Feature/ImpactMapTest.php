<?php

namespace Tests\Feature;

use App\Models\ImpactMap;
use App\Models\ImpactMapNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpactMapTest extends TestCase
{
    use RefreshDatabase;

    private function mapUrl(ImpactMap $map, string $suffix = ''): string
    {
        return '/impact-maps/'.$map->slug.$suffix;
    }

    private function makeMap(string $name = 'Grow mobile advertising', ?string $client = null): ImpactMap
    {
        $this->post('/impact-maps', ['name' => $name, 'client' => $client])->assertRedirect();

        return ImpactMap::query()->where('name', $name)->sole();
    }

    public function test_list_requires_login(): void
    {
        $this->flushSession();
        $this->get('/impact-maps')->assertRedirect(route('login'));
        $this->post('/impact-maps', ['name' => 'X'])->assertRedirect(route('login'));
        $this->assertSame(0, ImpactMap::query()->count());
    }

    public function test_list_works_when_logged_in(): void
    {
        $map = $this->makeMap();
        $this->get('/impact-maps')->assertOk()->assertSee('Impact Maps')->assertSee($this->mapUrl($map), false);
    }

    public function test_old_token_url_redirects_to_list(): void
    {
        $this->get('/impact-maps-b9ef02319b492d009c3d5c6f3f66b77b')->assertRedirect('/impact-maps');
    }

    public function test_map_gets_secret_slug_and_numeric_id_does_not_work(): void
    {
        $map = $this->makeMap();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $map->slug);
        $this->get('/impact-maps/'.$map->id)->assertNotFound();
        $this->get('/impact-maps/'.str_repeat('x', 32))->assertNotFound();
    }

    public function test_public_map_is_read_only_for_anonymous_visitors(): void
    {
        $map = $this->makeMap('Goal', 'Acme');
        $goal = $map->goal;
        $this->post($this->mapUrl($map, '/nodes/'.$goal->id), ['title' => 'Super-fans']);
        $actor = ImpactMapNode::query()->where('title', 'Super-fans')->sole();

        $this->flushSession();
        $this->get($this->mapUrl($map))->assertOk()
            ->assertSee('noindex', false)
            ->assertSee('Super-fans')->assertSee('<span class="client" style="text-align: right;">Acme</span>', false)->assertDontSee('· Acme')
            ->assertDontSee('class="tools"', false)
            ->assertDontSee('name="client"', false)
            ->assertDontSee('Delete map')
            ->assertDontSee('All maps');

        $this->post($this->mapUrl($map, '/nodes/'.$goal->id), ['title' => 'Hack'])->assertRedirect(route('login'));
        $this->patch($this->mapUrl($map, '/nodes/'.$actor->id), ['title' => 'Hack'])->assertRedirect(route('login'));
        $this->delete($this->mapUrl($map, '/nodes/'.$actor->id))->assertRedirect(route('login'));
        $this->patch($this->mapUrl($map), ['client' => 'Hack'])->assertRedirect(route('login'));
        $this->delete($this->mapUrl($map))->assertRedirect(route('login'));

        $this->assertSame('Super-fans', $actor->fresh()->title);
        $this->assertSame('Acme', $map->fresh()->client);
        $this->assertSame(2, ImpactMapNode::query()->count());
    }

    public function test_logged_in_user_gets_edit_ui_and_builds_five_levels(): void
    {
        $map = $this->makeMap();
        $goal = $map->goal;
        $this->assertSame(ImpactMapNode::GOAL, $goal->level);

        $parent = $goal;
        foreach (['Super-fans', 'Stay longer', 'Forums', 'Story 1'] as $level => $title) {
            $this->post($this->mapUrl($map, '/nodes/'.$parent->id), ['title' => $title])->assertRedirect($this->mapUrl($map));
            $parent = ImpactMapNode::query()->where('title', $title)->sole();
            $this->assertSame($level + 1, $parent->level);
        }
        $this->post($this->mapUrl($map, '/nodes/'.$parent->id), ['title' => 'Too deep'])->assertStatus(422);

        $this->get($this->mapUrl($map))->assertOk()
            ->assertSeeInOrder(['Goal', 'Actor', 'Impact', 'Deliverable'])
            ->assertSee('class="node lvl-4"', false)
            ->assertSee('class="tools"', false)
            ->assertDontSee('name="client"', false)
            ->assertDontSee('Delete map')
            ->assertDontSee('Save client')
            ->assertDontSee('Click a box to rename')
            ->assertDontSee('All maps')
            ->assertDontSee('name="name"', false)
            ->assertSee('<path d="M', false);
    }

    public function test_goal_rename_renames_map_and_deletes_cascade(): void
    {
        $map = $this->makeMap('Goal');
        $goal = $map->goal;
        $this->post($this->mapUrl($map, '/nodes/'.$goal->id), ['title' => 'Actor A']);
        $actor = ImpactMapNode::query()->where('title', 'Actor A')->sole();
        $this->post($this->mapUrl($map, '/nodes/'.$actor->id), ['title' => 'Impact A']);

        $this->patch($this->mapUrl($map, '/nodes/'.$actor->id), ['title' => 'Actor B']);
        $this->assertSame('Actor B', $actor->fresh()->title);
        $this->patch($this->mapUrl($map, '/nodes/'.$goal->id), ['title' => 'Renamed goal']);
        $this->assertSame('Renamed goal', $map->fresh()->name);
        $this->patch($this->mapUrl($map), ['name' => 'Ignored', 'client' => 'X']);
        $this->assertSame('Renamed goal', $map->fresh()->name);

        $this->delete($this->mapUrl($map, '/nodes/'.$goal->id))->assertStatus(422);
        $this->delete($this->mapUrl($map, '/nodes/'.$actor->id))->assertRedirect();
        $this->assertSame(1, ImpactMapNode::query()->count());

        $this->delete($this->mapUrl($map))->assertRedirect('/impact-maps');
        $this->assertSame(0, ImpactMap::query()->count());
        $this->assertSame(0, ImpactMapNode::query()->count());
    }

    public function test_client_is_optional_grouped_and_editable(): void
    {
        $a = $this->makeMap('Map A', '  Acme  ');
        $b = $this->makeMap('Map B', '');
        $this->assertSame('Acme', $a->client);
        $this->assertNull($b->client);

        $this->get('/impact-maps')->assertOk()->assertSeeInOrder(['Acme', 'Map A', 'No client / project', 'Map B'])
            ->assertSee('class="wrench"', false)
            ->assertDontSee('class="cog"', false)
            ->assertDontSee('nodes ·')
            ->assertSee(now()->timezone('Europe/Bucharest')->format('j M Y'))
            ->assertDontSee(now()->timezone('Europe/Bucharest')->format('j M Y H:i'))
            ->assertSee('class="modal-bg"', false)
            ->assertSee('x-data="{ create: false }"', false)
            ->assertSee('value="Acme"', false)
            ->assertSee('Delete map')
            ->assertSee($this->mapUrl($a), false);

        $this->patch($this->mapUrl($b), ['client' => 'Globex'])->assertRedirect('/impact-maps');
        $this->assertSame('Globex', $b->fresh()->client);
        $this->patch($this->mapUrl($a), ['client' => ''])->assertRedirect();
        $this->assertNull($a->fresh()->client);
    }

    public function test_node_from_another_map_is_rejected(): void
    {
        $one = $this->makeMap('One');
        $two = $this->makeMap('Two');

        $this->patch($this->mapUrl($one, '/nodes/'.$two->goal->id), ['title' => 'Hijack'])->assertNotFound();
    }

    public function test_validation_error_reopens_create_modal(): void
    {
        $this->from('/impact-maps')->post('/impact-maps', ['name' => ''])->assertRedirect('/impact-maps');
        $this->followingRedirects()->from('/impact-maps')->post('/impact-maps', ['name' => ''])
            ->assertOk()->assertSee('x-data="{ create: true }"', false);
        $this->assertSame(0, ImpactMap::query()->count());
    }

    public function test_client_dropdown_lists_existing_and_supports_none_and_new(): void
    {
        $this->makeMap('Map Z', 'Zeta');
        $this->makeMap('Map A', 'Acme');
        $this->makeMap('Map A2', 'Acme');

        $html = $this->get('/impact-maps')->assertOk()
            ->assertSee('<select name="client" id="map-client"', false)
            ->assertSeeInOrder(['id="map-client"', '— None —', '>Acme</option>', '>Zeta</option>', '+ New client…'], false)
            ->getContent();
        $modal = substr($html, strpos($html, 'id="map-client"'), strpos($html, '+ New client') - strpos($html, 'id="map-client"'));
        $this->assertSame(1, substr_count($modal, '>Acme</option>'));

        $this->post('/impact-maps', ['name' => 'No client', 'client' => ''])->assertRedirect();
        $this->assertNull(ImpactMap::query()->where('name', 'No client')->sole()->client);

        $this->post('/impact-maps', ['name' => 'Existing', 'client' => 'Zeta'])->assertRedirect();
        $this->assertSame('Zeta', ImpactMap::query()->where('name', 'Existing')->sole()->client);

        $this->post('/impact-maps', ['name' => 'Brand new', 'client' => '__new__', 'client_new' => '  Globex  '])->assertRedirect();
        $this->assertSame('Globex', ImpactMap::query()->where('name', 'Brand new')->sole()->client);

        $map = ImpactMap::query()->where('name', 'Existing')->sole();
        $this->patch($this->mapUrl($map), ['client' => '__new__', 'client_new' => 'Initech'])->assertRedirect('/impact-maps');
        $this->assertSame('Initech', $map->fresh()->client);
        $this->patch($this->mapUrl($map), ['client' => ''])->assertRedirect('/impact-maps');
        $this->assertNull($map->fresh()->client);
    }

    public function test_new_client_validation_reopens_modal_with_selection(): void
    {
        $this->followingRedirects()->from('/impact-maps')
            ->post('/impact-maps', ['name' => 'Keep me', 'client' => '__new__', 'client_new' => ''])
            ->assertOk()
            ->assertSee('x-data="{ create: true }"', false)
            ->assertSee('Type the new client / project name.')
            ->assertSee('value="Keep me"', false)
            ->assertSee('<option value="__new__" selected', false);
        $this->assertSame(0, ImpactMap::query()->count());
    }

    public function test_new_maps_go_to_top_and_new_clients_last(): void
    {
        $this->makeMap('Old A', 'Beta');
        $this->makeMap('New A', 'Beta');
        $this->makeMap('Loose', '');
        $this->makeMap('Alpha map', 'Alpha');

        $this->get('/impact-maps')->assertOk()
            ->assertSeeInOrder(['>Alpha<', 'Alpha map', '>Beta<', 'New A', 'Old A', 'No client / project', 'Loose'], false);
    }

    public function test_reorder_saves_group_and_map_order_and_moves_across_groups(): void
    {
        $a1 = $this->makeMap('A1', 'Acme');
        $a2 = $this->makeMap('A2', 'Acme');
        $z1 = $this->makeMap('Z1', 'Zeta');
        $n1 = $this->makeMap('N1', '');

        $this->postJson('/impact-maps/reorder', ['groups' => [
            ['client' => '', 'maps' => [$n1->slug]],
            ['client' => 'Zeta', 'maps' => [$z1->slug, $a1->slug]],
            ['client' => 'Acme', 'maps' => [$a2->slug]],
        ]])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame('Zeta', $a1->fresh()->client);
        $this->assertSame(1, $a1->fresh()->position);
        $this->get('/impact-maps')->assertOk()
            ->assertSeeInOrder(['No client / project', 'N1', '>Zeta<', 'Z1', 'A1', '>Acme<', 'A2'], false)
            ->assertSee('data-slug="'.$a1->slug.'"', false);

        $this->postJson('/impact-maps/reorder', ['groups' => [
            ['client' => 'Acme', 'maps' => [$a2->slug, $a1->slug]],
        ]])->assertOk();
        $this->assertSame('Acme', $a1->fresh()->client);
        $this->assertNull($n1->fresh()->client);
    }

    public function test_reorder_requires_login_and_valid_payload(): void
    {
        $map = $this->makeMap('A1', 'Acme');
        $this->postJson('/impact-maps/reorder', ['groups' => 'nope'])->assertStatus(422);

        $this->flushSession();
        $this->post('/impact-maps/reorder', ['groups' => [['client' => 'Hack', 'maps' => [$map->slug]]]])->assertRedirect(route('login'));
        $this->assertSame('Acme', $map->fresh()->client);
    }

    private function kids(ImpactMap $map, ImpactMapNode $parent, array $titles): array
    {
        foreach ($titles as $t) {
            $this->post($this->mapUrl($map, '/nodes/'.$parent->id), ['title' => $t]);
        }

        return ImpactMapNode::query()->where('parent_id', $parent->id)->orderBy('position')->get()->all();
    }

    public function test_sibling_nodes_can_be_reordered(): void
    {
        $map = $this->makeMap('Goal');
        [$a, $b, $c] = $this->kids($map, $map->goal, ['Actor A', 'Actor B', 'Actor C']);

        $this->get($this->mapUrl($map))->assertOk()
            ->assertSeeInOrder(['Actor A', 'Actor B', 'Actor C'])
            ->assertSee('data-reorder="'.url('/impact-maps/'.$map->slug.'/nodes/'.$map->goal->id.'/reorder').'"', false);

        $this->postJson($this->mapUrl($map, '/nodes/'.$map->goal->id.'/reorder'), ['children' => [$c->id, $a->id, $b->id]])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertSame([0, 1, 2], [$c->fresh()->position, $a->fresh()->position, $b->fresh()->position]);
        $this->get($this->mapUrl($map))->assertOk()->assertSeeInOrder(['Actor C', 'Actor A', 'Actor B']);
        $this->assertSame($map->goal->id, $c->fresh()->parent_id);
    }

    public function test_reorder_rejects_foreign_or_missing_children(): void
    {
        $map = $this->makeMap('Goal');
        [$a, $b] = $this->kids($map, $map->goal, ['Actor A', 'Actor B']);
        [$i1] = $this->kids($map, $a, ['Impact 1']);
        $other = $this->makeMap('Other');
        [$x] = $this->kids($other, $other->goal, ['Foreign']);
        $url = $this->mapUrl($map, '/nodes/'.$map->goal->id.'/reorder');

        $this->postJson($url, ['children' => [$b->id, $i1->id]])->assertStatus(422);   // grandchild (reparent)
        $this->postJson($url, ['children' => [$b->id, $a->id, $x->id]])->assertStatus(422); // other map
        $this->postJson($url, ['children' => [$b->id]])->assertStatus(422);            // incomplete
        $this->postJson($this->mapUrl($map, '/nodes/'.$other->goal->id.'/reorder'), ['children' => [$x->id]])->assertNotFound();
        $this->assertSame([$a->position, $b->position], [$a->fresh()->position, $b->fresh()->position]);
        $this->assertSame($a->id, $i1->fresh()->parent_id);
    }

    public function test_reorder_requires_login_and_public_view_has_no_drag(): void
    {
        $map = $this->makeMap('Goal');
        [$a, $b] = $this->kids($map, $map->goal, ['Actor A', 'Actor B']);

        $this->flushSession();
        $this->post($this->mapUrl($map, '/nodes/'.$map->goal->id.'/reorder'), ['children' => [$b->id, $a->id]])
            ->assertRedirect(route('login'));
        $this->assertSame($a->position, $a->fresh()->position);
        $this->get($this->mapUrl($map))->assertOk()->assertDontSee('data-reorder=', false);
    }

    public function test_epics_and_stories_labels_on_topmost_nodes_only(): void
    {
        $map = $this->makeMap('Goal');
        [$actor] = $this->kids($map, $map->goal, ['Actor']);
        [$impact] = $this->kids($map, $actor, ['Impact']);

        $this->get($this->mapUrl($map))->assertOk()->assertDontSee("<span class=\"sub-label", false);

        [$e1, $e2] = $this->kids($map, $impact, ['Epic one', 'Epic two']);
        $this->get($this->mapUrl($map))->assertOk()
            ->assertSee('<span class="sub-label sub-3" aria-hidden="true">Epics</span>', false)
            ->assertDontSee('>Stories</span>', false);

        $this->kids($map, $e2, ['Story x']);
        $this->kids($map, $e1, ['Story a', 'Story b']);

        $this->flushSession();
        $html = $this->get($this->mapUrl($map))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '>Epics</span>'));
        $this->assertSame(1, substr_count($html, '>Stories</span>'));
        $this->assertMatchesRegularExpression('/>Epics<\/span>\s*<span class="t"[^>]*>Epic one</', $html);
        $this->assertMatchesRegularExpression('/>Stories<\/span>\s*<span class="t"[^>]*>Story a</', $html);
    }

    public function test_progress_percentage_increasing_and_decreasing(): void
    {
        $up = new ImpactMap(['track_start' => 0, 'track_target' => 200]);
        $this->assertEqualsWithDelta(42.0, $up->progressPct(84), 0.001);
        $this->assertEqualsWithDelta(150.0, $up->progressPct(300), 0.001);

        $down = new ImpactMap(['track_start' => 20, 'track_target' => 5]); // e.g. churn 20% -> 5%
        $this->assertEqualsWithDelta(0.0, $down->progressPct(20), 0.001);
        $this->assertEqualsWithDelta(40.0, $down->progressPct(14), 0.001);
        $this->assertEqualsWithDelta(100.0, $down->progressPct(5), 0.001);
        $this->assertEqualsWithDelta(-20.0, $down->progressPct(23), 0.001);

        $this->assertFalse((new ImpactMap(['track_start' => 3, 'track_target' => 3]))->hasTracker());
    }

    public function test_tracker_and_checkins_crud(): void
    {
        $map = $this->makeMap('Goal');
        $this->get($this->mapUrl($map))->assertOk()->assertSee('class="track-toggle"', false)->assertSee('Set start &amp; goal', false);

        $this->put($this->mapUrl($map, '/tracker'), ['track_start' => '5', 'track_target' => '5'])->assertSessionHasErrors('track_target');
        $this->put($this->mapUrl($map, '/tracker'), ['track_start' => 'abc', 'track_target' => '9'])->assertSessionHasErrors('track_start');

        $this->put($this->mapUrl($map, '/tracker'), ['track_start' => '20', 'track_start_label' => '% retention rate', 'track_target' => '45', 'track_target_label' => '% retention rate'])
            ->assertRedirect($this->mapUrl($map).'#track');
        $map->refresh();
        $this->assertSame(20.0, $map->track_start);
        $this->assertSame('% retention rate', $map->track_target_label);

        $this->post($this->mapUrl($map, '/checkins'), ['checkin_value' => 'x', 'checkin_date' => '2026-11-03'])->assertSessionHasErrors('checkin_value');
        $this->post($this->mapUrl($map, '/checkins'), ['checkin_value' => '25', 'checkin_date' => '2026-10-01', 'checkin_note' => 'Onboarding emails'])->assertRedirect($this->mapUrl($map).'#track');
        $this->post($this->mapUrl($map, '/checkins'), ['checkin_value' => '30', 'checkin_date' => '2026-11-01']);
        $this->assertSame(2, $map->checkins()->count());

        $this->get($this->mapUrl($map))->assertOk()
            ->assertSee('40% of the way')
            ->assertSee('--fill: 40%', false)
            ->assertSee('Onboarding emails')
            ->assertSee('Edit start &amp; goal', false)
            ->assertSee('class="ck-del"', false);

        $first = $map->checkins()->first();
        $this->delete($this->mapUrl($map, '/checkins/'.$first->id))->assertRedirect($this->mapUrl($map).'#track');
        $this->assertSame(1, $map->checkins()->count());

        $other = $this->makeMap('Other');
        $last = $map->checkins()->first();
        $this->delete($this->mapUrl($other, '/checkins/'.$last->id))->assertNotFound();

        $this->post($this->mapUrl($map, '/checkins'), ['checkin_value' => '60', 'checkin_date' => '2026-11-03']);
        $this->get($this->mapUrl($map))->assertSee('Goal reached! (160%)')->assertSee('--fill: 100%', false);

        $this->delete($this->mapUrl($map, '/tracker'))->assertRedirect($this->mapUrl($map).'#track');
        $map->refresh();
        $this->assertFalse($map->hasTracker());
        $this->assertSame(0, $map->checkins()->count());
    }

    public function test_checkin_requires_tracker_and_decreasing_goal_renders(): void
    {
        $map = $this->makeMap('Churn');
        $this->post($this->mapUrl($map, '/checkins'), ['checkin_value' => '1', 'checkin_date' => '2026-11-03'])->assertSessionHasErrors('checkin_value');

        $this->put($this->mapUrl($map, '/tracker'), ['track_start' => '20', 'track_target' => '5', 'track_target_label' => '% churn']);
        $this->post($this->mapUrl($map, '/checkins'), ['checkin_value' => '14', 'checkin_date' => '2026-11-03']);
        $this->get($this->mapUrl($map))->assertOk()->assertSee('40% of the way')->assertSee('left: 40%', false);
    }

    public function test_tracker_public_read_only_and_auth(): void
    {
        $map = $this->makeMap('Goal');
        $this->flushSession();
        $this->get($this->mapUrl($map))->assertOk()->assertDontSee('class="track-toggle"', false);
        $this->put($this->mapUrl($map, '/tracker'), ['track_start' => '0', 'track_target' => '10'])->assertRedirect(route('login'));
        $this->assertFalse($map->fresh()->hasTracker());

        $map->update(['track_start' => 0, 'track_target' => 1000, 'track_target_label' => 'users']);
        $c = $map->checkins()->create(['value' => 420, 'date' => '2026-11-03']);

        $this->get($this->mapUrl($map))->assertOk()
            ->assertSee('class="track-toggle"', false)->assertSee('42% of the way')
            ->assertDontSee('name="checkin_value"', false)
            ->assertDontSee('name="track_start"', false)
            ->assertDontSee('class="ck-del"', false)
            ->assertDontSee('Remove tracker');

        $this->post($this->mapUrl($map, '/checkins'), ['checkin_value' => '1', 'checkin_date' => '2026-11-03'])->assertRedirect(route('login'));
        $this->delete($this->mapUrl($map, '/checkins/'.$c->id))->assertRedirect(route('login'));
        $this->delete($this->mapUrl($map, '/tracker'))->assertRedirect(route('login'));
        $this->assertSame(1, $map->checkins()->count());
        $this->assertTrue($map->fresh()->hasTracker());
    }
}
