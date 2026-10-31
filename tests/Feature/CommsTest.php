<?php

namespace Tests\Feature;

use App\Enums\CommunicationType;
use App\Livewire\Comms;
use App\Models\Communication;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommsTest extends TestCase
{
    use RefreshDatabase;

    public function test_comms_page_renders_the_month_and_partner_clients(): void
    {
        $this->travelTo('2026-10-28 12:00:00');

        Project::query()->create([
            'hub' => 'partners',
            'name' => 'Tudia',
            'slug' => 'tudia',
            'position' => 1,
        ]);
        Project::query()->create([
            'hub' => 'partners',
            'name' => 'Old Client',
            'slug' => 'old-client',
            'position' => 2,
            'is_archived' => true,
        ]);
        Project::query()->create([
            'hub' => 'partners',
            'name' => '',
            'slug' => 'delimiter-1',
            'position' => 3,
            'is_delimiter' => true,
        ]);
        Project::query()->create([
            'hub' => 'assets',
            'name' => 'Radiologie',
            'slug' => 'radiologie',
            'position' => 1,
        ]);
        Project::query()->create([
            'hub' => 'assets',
            'name' => 'Old Asset',
            'slug' => 'old-asset',
            'position' => 2,
            'is_archived' => true,
        ]);

        $this->get(route('comms'))
            ->assertOk()
            ->assertSee('Comms')
            ->assertSee('OCTOBER 2026')
            ->assertSeeInOrder(['Clients', 'Key people', 'Assets'])
            ->assertSee('Tudia')
            ->assertSee('Radiologie')
            ->assertSee('No key people')
            ->assertDontSee('Add entry')
            ->assertDontSee('Old Client')
            ->assertDontSee('Old Asset')
            ->assertSeeLivewire(Comms::class);
    }

    public function test_nav_shows_comms_after_roadmaps(): void
    {
        $this->get(route('milestones'))
            ->assertSeeInOrder(['Roadmaps', 'Comms', 'Links']);
    }

    public function test_can_log_a_communication_on_a_selected_day(): void
    {
        $this->travelTo('2026-10-28 12:00:00');

        $client = Project::query()->create([
            'hub' => 'partners',
            'name' => 'Tudia',
            'slug' => 'tudia',
            'position' => 1,
        ]);

        Livewire::test(Comms::class)
            ->call('selectDay', '2026-10-12')
            ->assertSet('adding', true)
            ->assertSee('Add entry')
            ->set('projectId', (string) $client->id)
            ->set('type', CommunicationType::ReviewPlanning->value)
            ->call('addEntry')
            ->assertSet('adding', false)
            ->assertSet('month', '2026-10')
            ->assertSee('Tudia')
            ->assertSee('review & planning meeting');

        $entry = Communication::query()->first();

        $this->assertNotNull($entry);
        $this->assertSame($client->id, $entry->project_id);
        $this->assertSame(CommunicationType::ReviewPlanning, $entry->type);
        $this->assertSame('2026-10-12', $entry->occurred_on->toDateString());
    }

    public function test_day_modal_offers_who_and_type_as_buttons(): void
    {
        Project::query()->create([
            'hub' => 'partners',
            'name' => 'Tudia',
            'slug' => 'tudia',
            'position' => 1,
        ]);

        Livewire::test(Comms::class)
            ->call('selectDay', '2026-10-12')
            ->assertSee('who?')
            ->assertSee('key people')
            ->assertSee('Choose a client')
            ->assertSee('Tudia')
            ->assertSee('quick update')
            ->assertSee('review & planning meeting')
            ->assertDontSee('Choose a type')
            ->assertSee('<select', false)
            ->call('setAudience', 'people')
            ->assertSee('Choose a person')
            ->assertSee('Add person');
    }

    public function test_switching_who_clears_the_previous_selection(): void
    {
        $client = Project::query()->create([
            'hub' => 'partners',
            'name' => 'Tudia',
            'slug' => 'tudia',
            'position' => 1,
        ]);

        Livewire::test(Comms::class)
            ->set('projectId', (string) $client->id)
            ->call('setAudience', 'assets')
            ->assertSet('audience', 'assets')
            ->assertSet('projectId', '');
    }

    public function test_can_log_a_communication_for_an_asset(): void
    {
        $this->travelTo('2026-10-28 12:00:00');

        $asset = Project::query()->create([
            'hub' => 'assets',
            'name' => 'Radiologie',
            'slug' => 'radiologie',
            'position' => 1,
        ]);

        Livewire::test(Comms::class)
            ->call('selectDay', '2026-10-12')
            ->call('setAudience', 'assets')
            ->call('selectProject', $asset->id)
            ->call('setType', CommunicationType::QuickUpdate->value)
            ->call('addEntry')
            ->assertSet('adding', false)
            ->assertSee('Radiologie');

        $entry = Communication::query()->first();

        $this->assertNotNull($entry);
        $this->assertSame($asset->id, $entry->project_id);
        $this->assertNull($entry->person_id);
        $this->assertSame(CommunicationType::QuickUpdate, $entry->type);
        $this->assertSame('2026-10-12', $entry->occurred_on->toDateString());
    }

    public function test_cannot_select_an_asset_while_clients_are_shown(): void
    {
        $asset = Project::query()->create([
            'hub' => 'assets',
            'name' => 'Radiologie',
            'slug' => 'radiologie',
            'position' => 1,
        ]);

        Livewire::test(Comms::class)
            ->call('selectProject', $asset->id)
            ->assertSet('projectId', '');
    }

    public function test_can_add_a_key_person_and_log_a_communication(): void
    {
        $this->travelTo('2026-10-28 12:00:00');

        Person::factory()->create([
            'name' => 'Existing Person',
        ]);

        Livewire::test(Comms::class)
            ->call('selectDay', '2026-10-12')
            ->call('setAudience', 'people')
            ->assertSee('Add person')
            ->assertSee('Existing Person')
            ->set('newPersonName', 'Alex Pal')
            ->call('addPerson')
            ->assertSet('addingPerson', false)
            ->call('setType', CommunicationType::ReviewPlanning->value)
            ->call('addEntry')
            ->assertSet('adding', false)
            ->assertSee('Alex Pal');

        $person = Person::query()->where('name', 'Alex Pal')->first();

        $this->assertNotNull($person);

        $entry = Communication::query()->where('person_id', $person->id)->first();

        $this->assertNotNull($entry);
        $this->assertNull($entry->project_id);
        $this->assertSame(CommunicationType::ReviewPlanning, $entry->type);
        $this->assertSame('2026-10-12', $entry->occurred_on->toDateString());
    }

    public function test_blank_key_person_name_is_not_saved(): void
    {
        Livewire::test(Comms::class)
            ->set('newPersonName', '   ')
            ->call('addPerson');

        $this->assertDatabaseCount('people', 0);
    }

    public function test_rejects_a_key_person_name_longer_than_255_characters(): void
    {
        Livewire::test(Comms::class)
            ->set('newPersonName', str_repeat('a', 256))
            ->call('addPerson');

        $this->assertDatabaseCount('people', 0);
    }

    public function test_rejects_an_unknown_key_person(): void
    {
        Livewire::test(Comms::class)
            ->set('occurredOn', '2026-10-12')
            ->set('audience', 'people')
            ->set('personId', '999')
            ->set('type', CommunicationType::QuickUpdate->value)
            ->call('addEntry');

        $this->assertDatabaseCount('communications', 0);
    }

    public function test_key_person_name_is_escaped_when_rendered(): void
    {
        $this->travelTo('2026-10-28 12:00:00');

        $person = Person::factory()->create([
            'name' => '<script>alert(1)</script>',
        ]);

        Communication::query()->create([
            'person_id' => $person->id,
            'type' => CommunicationType::QuickUpdate,
            'occurred_on' => '2026-10-28',
        ]);

        Livewire::test(Comms::class)
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_rejects_a_client_outside_the_active_partner_list(): void
    {
        $asset = Project::query()->create([
            'hub' => 'assets',
            'name' => 'Radiologie',
            'slug' => 'radiologie',
            'position' => 1,
        ]);

        Livewire::test(Comms::class)
            ->set('occurredOn', '2026-10-12')
            ->set('projectId', (string) $asset->id)
            ->set('type', CommunicationType::QuickUpdate->value)
            ->call('addEntry');

        $this->assertDatabaseCount('communications', 0);
    }

    public function test_rejects_an_unknown_communication_type(): void
    {
        $client = Project::query()->create([
            'hub' => 'partners',
            'name' => 'Tudia',
            'slug' => 'tudia',
            'position' => 1,
        ]);

        Livewire::test(Comms::class)
            ->set('occurredOn', '2026-10-12')
            ->set('projectId', (string) $client->id)
            ->set('type', 'email')
            ->call('addEntry');

        $this->assertDatabaseCount('communications', 0);
    }

    public function test_clients_panel_shows_how_long_since_the_last_communication(): void
    {
        $this->travelTo('2026-10-28 12:00:00');

        $recent = Project::query()->create([
            'hub' => 'partners',
            'name' => 'Tudia',
            'slug' => 'tudia',
            'position' => 1,
        ]);
        $quiet = Project::query()->create([
            'hub' => 'partners',
            'name' => 'Northwind',
            'slug' => 'northwind',
            'position' => 2,
        ]);

        Communication::factory()->create([
            'project_id' => $recent->id,
            'type' => CommunicationType::QuickUpdate,
            'occurred_on' => '2026-10-17',
        ]);
        Communication::factory()->create([
            'project_id' => $recent->id,
            'type' => CommunicationType::ReviewPlanning,
            'occurred_on' => '2026-10-14',
        ]);
        Communication::factory()->create([
            'project_id' => $recent->id,
            'type' => CommunicationType::ReviewPlanning,
            'occurred_on' => '2026-10-01',
        ]);

        Livewire::test(Comms::class)
            ->assertSee('Last comm.: 1 week + 4 days ago')
            ->assertSee('text-mark')
            ->assertSee('text-red-400')
            ->assertSee('Last review meeting: 2 weeks ago')
            ->assertSee('Last comm.: none yet')
            ->assertSee('Last review meeting: none yet')
            ->assertSeeInOrder(['Northwind', 'Tudia']);

        $this->assertNull($quiet->communications()->first());
    }

    public function test_assets_and_people_panels_show_how_long_since_the_last_communication(): void
    {
        $this->travelTo('2026-10-28 12:00:00');

        $asset = Project::query()->create([
            'hub' => 'assets',
            'name' => 'Radiologie',
            'slug' => 'radiologie',
            'position' => 1,
        ]);
        $person = Person::factory()->create([
            'name' => 'Alex Pal',
        ]);

        Communication::query()->create([
            'project_id' => $asset->id,
            'type' => CommunicationType::ReviewPlanning,
            'occurred_on' => '2026-10-21',
        ]);
        Communication::query()->create([
            'person_id' => $person->id,
            'type' => CommunicationType::QuickUpdate,
            'occurred_on' => '2026-10-28',
        ]);

        Livewire::test(Comms::class)
            ->assertSeeInOrder(['Key people', 'Alex Pal', 'Assets', 'Radiologie'])
            ->assertSee('Last comm.: 1 week ago')
            ->assertSee('Last review meeting: 1 week ago')
            ->assertSee('Last comm.: today')
            ->assertSee('Last review meeting: none yet');
    }

    public function test_can_delete_a_communication(): void
    {
        $entry = Communication::factory()->create();

        Livewire::test(Comms::class)
            ->call('deleteEntry', $entry->id);

        $this->assertModelMissing($entry);
    }
}
