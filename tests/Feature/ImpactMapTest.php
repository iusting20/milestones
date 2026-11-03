<?php

namespace Tests\Feature;

use App\Models\ImpactMap;
use App\Models\ImpactMapNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpactMapTest extends TestCase
{
    use RefreshDatabase;

    protected bool $authenticate = false;

    private const TOKEN = 'test-secret-token-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        config(['impact_maps.token' => self::TOKEN]);
    }

    private function url(string $suffix = ''): string
    {
        return '/impact-maps-'.self::TOKEN.$suffix;
    }

    public function test_secret_url_works_without_login(): void
    {
        $this->get($this->url())->assertOk()->assertSee('Impact Maps')->assertSee('noindex', false);
    }

    public function test_wrong_or_missing_token_404s(): void
    {
        $this->get('/impact-maps-wrong-token-0123456789abcdef')->assertNotFound();
        $this->get('/impact-maps')->assertNotFound();
        config(['impact_maps.token' => '']);
        $this->get($this->url())->assertNotFound();
    }

    public function test_create_map_adds_goal_and_renders_tree(): void
    {
        $this->post($this->url(), ['name' => 'Grow mobile advertising'])->assertRedirect();

        $map = ImpactMap::query()->sole();
        $goal = $map->goal;
        $this->assertSame(ImpactMapNode::GOAL, $goal->level);
        $this->assertSame('Grow mobile advertising', $goal->title);

        $this->post($this->url('/'.$map->id.'/nodes/'.$goal->id), ['title' => 'Super-fans'])->assertRedirect();
        $actor = ImpactMapNode::query()->where('title', 'Super-fans')->sole();
        $this->assertSame(1, $actor->level);
        $this->post($this->url('/'.$map->id.'/nodes/'.$actor->id), ['title' => 'Stay longer']);
        $impact = ImpactMapNode::query()->where('title', 'Stay longer')->sole();
        $this->post($this->url('/'.$map->id.'/nodes/'.$impact->id), ['title' => 'Forums']);
        $deliverable = ImpactMapNode::query()->where('title', 'Forums')->sole();
        $this->post($this->url('/'.$map->id.'/nodes/'.$deliverable->id), ['title' => 'Story 1']);
        $story = ImpactMapNode::query()->where('title', 'Story 1')->sole();
        $this->assertSame(4, $story->level);
        $this->post($this->url('/'.$map->id.'/nodes/'.$story->id), ['title' => 'Too deep'])->assertStatus(422);

        $this->get($this->url('/'.$map->id))->assertOk()
            ->assertSeeInOrder(['Goal', 'Actor', 'Impact', 'Deliverable'])
            ->assertSee('Super-fans')->assertSee('Story 1')
            ->assertSee('class="node lvl-4"', false)
            ->assertSee('<path d="M', false);
    }

    public function test_rename_and_delete_nodes_and_map(): void
    {
        $this->post($this->url(), ['name' => 'Goal']);
        $map = ImpactMap::query()->sole();
        $goal = $map->goal;
        $this->post($this->url('/'.$map->id.'/nodes/'.$goal->id), ['title' => 'Actor A']);
        $actor = ImpactMapNode::query()->where('title', 'Actor A')->sole();
        $this->post($this->url('/'.$map->id.'/nodes/'.$actor->id), ['title' => 'Impact A']);

        $this->patch($this->url('/'.$map->id.'/nodes/'.$actor->id), ['title' => 'Actor B'])->assertRedirect();
        $this->assertSame('Actor B', $actor->fresh()->title);

        $this->patch($this->url('/'.$map->id.'/nodes/'.$goal->id), ['title' => 'Renamed goal'])->assertRedirect();
        $this->assertSame('Renamed goal', $map->fresh()->name);
        $this->patch($this->url('/'.$map->id), ['name' => 'Ignored', 'client' => 'X'])->assertRedirect();
        $this->assertSame('Renamed goal', $map->fresh()->name);
        $this->get($this->url('/'.$map->id))->assertOk()->assertDontSee('name="name"', false)->assertDontSee('>Rename<', false);

        $this->delete($this->url('/'.$map->id.'/nodes/'.$goal->id))->assertStatus(422);
        $this->delete($this->url('/'.$map->id.'/nodes/'.$actor->id))->assertRedirect();
        $this->assertSame(1, ImpactMapNode::query()->count());

        $this->delete($this->url('/'.$map->id))->assertRedirect($this->url());
        $this->assertSame(0, ImpactMap::query()->count());
        $this->assertSame(0, ImpactMapNode::query()->count());
    }

    public function test_client_is_optional_set_on_create_editable_and_shown(): void
    {
        $this->post($this->url(), ['name' => 'Map A', 'client' => '  Acme  ']);
        $this->post($this->url(), ['name' => 'Map B', 'client' => '']);
        [$a, $b] = ImpactMap::query()->orderBy('id')->get()->all();
        $this->assertSame('Acme', $a->client);
        $this->assertNull($b->client);

        $this->get($this->url())->assertOk()
            ->assertSeeInOrder(['Acme', 'Map A', 'No client / project', 'Map B']);
        $this->get($this->url('/'.$a->id))->assertOk()->assertSee('· Acme')->assertSee('name="client" value="Acme"', false);

        $this->patch($this->url('/'.$b->id), ['client' => 'Globex'])->assertRedirect();
        $this->assertSame('Globex', $b->fresh()->client);
        $this->patch($this->url('/'.$a->id), ['client' => ''])->assertRedirect();
        $this->assertNull($a->fresh()->client);
    }

    public function test_node_from_another_map_is_rejected(): void
    {
        $this->post($this->url(), ['name' => 'One']);
        $this->post($this->url(), ['name' => 'Two']);
        [$one, $two] = ImpactMap::query()->orderBy('id')->get()->all();

        $this->patch($this->url('/'.$one->id.'/nodes/'.$two->goal->id), ['title' => 'Hijack'])->assertNotFound();
    }
}
