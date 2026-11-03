<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAuthenticated;
use App\Models\ImpactMap;
use App\Models\ImpactMapNode;
use App\Support\ImpactMapLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ImpactMapController extends Controller
{
    public const NEW_CLIENT = '__new__';

    public function index(): View
    {
        return view('impact-maps.index', [
            'maps' => $this->orderedGroups(),
            'clients' => ImpactMap::query()->whereNotNull('client')->where('client', '!=', '')
                ->distinct()->orderBy('client')->pluck('client'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $client = $this->client($request);

        $map = DB::transaction(function () use ($data, $client) {
            // New maps go to the top of their group.
            $top = ImpactMap::query()->where(fn ($q) => $client === null ? $q->whereNull('client')->orWhere('client', '') : $q->where('client', $client))->min('position');
            $map = ImpactMap::query()->create(['name' => trim($data['name']), 'client' => $client]);
            $map->forceFill(['position' => $top === null ? 0 : $top - 1])->save();
            $map->nodes()->create(['level' => ImpactMapNode::GOAL, 'title' => trim($data['name']), 'position' => 0]);

            return $map;
        });

        return redirect()->route('impact-maps.show', $this->params($map));
    }

    public function show(Request $request): View
    {
        $map = $this->map($request);

        return view('impact-maps.show', [
            'map' => $map,
            'layout' => ImpactMapLayout::compute($map->nodes()->get()),
            'levels' => ImpactMapNode::LEVELS,
            'columns' => ImpactMapLayout::COLUMNS,
            // Public link: anonymous visitors get a read-only view; logged-in users can edit.
            'canEdit' => $request->session()->get(EnsureAuthenticated::SESSION_KEY) === true,
        ]);
    }

    /** Map name follows the goal node; only the client/project is edited here. */
    public function update(Request $request): RedirectResponse
    {
        $map = $this->map($request);
        $map->update(['client' => $this->client($request)]);

        return redirect()->route('impact-maps.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->map($request)->delete();

        return redirect()->route('impact-maps.index');
    }

    public function storeNode(Request $request): RedirectResponse
    {
        $map = $this->map($request);
        $parent = $this->node($request, $map);
        abort_if($parent->level >= ImpactMapNode::STORY, 422);
        $data = $request->validate(['title' => 'required|string|max:255']);

        $map->nodes()->create([
            'parent_id' => $parent->id,
            'level' => $parent->level + 1,
            'title' => trim($data['title']),
            'position' => (int) $map->nodes()->where('parent_id', $parent->id)->max('position') + 1,
        ]);
        $map->touch();

        return redirect()->route('impact-maps.show', $this->params($map));
    }

    public function updateNode(Request $request): RedirectResponse
    {
        $map = $this->map($request);
        $node = $this->node($request, $map);
        $data = $request->validate(['title' => 'required|string|max:255']);
        $node->update(['title' => trim($data['title'])]);
        if ($node->parent_id === null) {
            $map->update(['name' => $node->title]);
        }
        $map->touch();

        return redirect()->route('impact-maps.show', $this->params($map));
    }

    public function destroyNode(Request $request): RedirectResponse
    {
        $map = $this->map($request);
        $node = $this->node($request, $map);
        abort_if($node->parent_id === null, 422, 'The goal cannot be deleted.');

        DB::transaction(function () use ($node) {
            $ids = [$node->id];
            $frontier = [$node->id];
            while ($frontier !== []) {
                $frontier = ImpactMapNode::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
                $ids = array_merge($ids, $frontier);
            }
            ImpactMapNode::query()->whereIn('id', $ids)->delete();
        });
        $map->touch();

        return redirect()->route('impact-maps.show', $this->params($map));
    }

    private function map(Request $request): ImpactMap
    {
        return ImpactMap::query()->where('slug', (string) $request->route('map'))->firstOrFail();
    }

    private function node(Request $request, ImpactMap $map): ImpactMapNode
    {
        return $map->nodes()->findOrFail((int) $request->route('node'));
    }

    /** @return array<string, mixed> */
    private function params(ImpactMap $map): array
    {
        return ['map' => $map->slug];
    }

    /** Client picker: an existing value, empty (none), or NEW_CLIENT plus a typed client_new. */
    private function client(Request $request): ?string
    {
        $data = $request->validate([
            'client' => 'nullable|string|max:255',
            'client_new' => 'nullable|string|max:255|required_if:client,'.self::NEW_CLIENT,
        ], ['client_new.required_if' => 'Type the new client / project name.']);
        $client = ($data['client'] ?? null) === self::NEW_CLIENT ? ($data['client_new'] ?? '') : ($data['client'] ?? '');

        return trim((string) $client) ?: null;
    }

    /**
     * Save drag-and-drop order. Payload: groups = [{client: '', maps: [slug, ...]}, ...] in display order.
     * A map dropped into another group takes that group's client.
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'groups' => 'required|array|max:500',
            'groups.*.client' => 'nullable|string|max:255',
            'groups.*.maps' => 'present|array|max:2000',
            'groups.*.maps.*' => 'string|max:64',
        ]);

        DB::transaction(function () use ($data) {
            $now = now();
            foreach (array_values($data['groups']) as $g => $group) {
                $client = trim((string) ($group['client'] ?? ''));
                DB::table('impact_map_groups')->upsert(
                    [['client' => $client, 'position' => $g, 'created_at' => $now, 'updated_at' => $now]],
                    ['client'], ['position', 'updated_at']
                );
                foreach (array_values($group['maps']) as $p => $slug) {
                    ImpactMap::query()->where('slug', $slug)->update(['position' => $p, 'client' => $client === '' ? null : $client]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Groups in saved order (unsaved clients after, alphabetically; "No client" last by default); maps by position. */
    private function orderedGroups(): Collection
    {
        $order = DB::table('impact_map_groups')->pluck('position', 'client');
        $groups = ImpactMap::query()->withCount('nodes')->orderBy('position')->orderByDesc('updated_at')->get()
            ->groupBy(fn (ImpactMap $m) => (string) $m->client);

        return $groups->sortBy(fn ($maps, $client) => [
            $order->has($client) ? 0 : 1,
            $order->get($client, 0),
            $client === '' ? 1 : 0,
            mb_strtolower((string) $client),
        ]);
    }

    /** Reorder the children of {node}: children = [id, ...] must be exactly that parent's children (no reparenting). */
    public function reorderChildren(Request $request): JsonResponse
    {
        $map = $this->map($request);
        $parent = $map->nodes()->whereKey((int) $request->route('node'))->firstOrFail();
        $ids = array_map('intval', $request->validate([
            'children' => 'required|array|max:500',
            'children.*' => 'integer|distinct',
        ])['children']);

        $current = $map->nodes()->where('parent_id', $parent->id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $given = $ids;
        sort($given);
        abort_unless($given === $current, 422, 'Children do not match this parent.');

        DB::transaction(function () use ($ids, $map) {
            foreach ($ids as $p => $id) {
                $map->nodes()->whereKey($id)->update(['position' => $p]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Set / edit the progress tracker's start and goal. */
    public function updateTracker(Request $request): RedirectResponse
    {
        $map = $this->map($request);
        $d = $request->validate([
            'track_start' => 'required|numeric|between:-1000000000000,1000000000000',
            'track_start_label' => 'nullable|string|max:100',
            'track_target' => 'required|numeric|between:-1000000000000,1000000000000',
            'track_target_label' => 'nullable|string|max:100',
        ]);
        if ((float) $d['track_start'] === (float) $d['track_target']) {
            throw ValidationException::withMessages(['track_target' => 'The goal must be different from the start.']);
        }

        $map->update([
            'track_start' => (float) $d['track_start'],
            'track_start_label' => trim((string) ($d['track_start_label'] ?? '')) ?: null,
            'track_target' => (float) $d['track_target'],
            'track_target_label' => trim((string) ($d['track_target_label'] ?? '')) ?: null,
        ]);

        return $this->toTracker($map);
    }

    public function destroyTracker(Request $request): RedirectResponse
    {
        $map = $this->map($request);
        DB::transaction(function () use ($map) {
            $map->checkins()->delete();
            $map->update(['track_start' => null, 'track_start_label' => null, 'track_target' => null, 'track_target_label' => null]);
        });

        return $this->toTracker($map);
    }

    public function storeCheckin(Request $request): RedirectResponse
    {
        $map = $this->map($request);
        if (! $map->hasTracker()) {
            throw ValidationException::withMessages(['checkin_value' => 'Set a start and goal first.']);
        }
        $d = $request->validate([
            'checkin_value' => 'required|numeric|between:-1000000000000,1000000000000',
            'checkin_date' => 'required|date',
            'checkin_note' => 'nullable|string|max:255',
        ]);

        $map->checkins()->create([
            'value' => (float) $d['checkin_value'],
            'date' => $d['checkin_date'],
            'note' => trim((string) ($d['checkin_note'] ?? '')) ?: null,
        ]);

        return $this->toTracker($map);
    }

    public function destroyCheckin(Request $request): RedirectResponse
    {
        $map = $this->map($request);
        $map->checkins()->whereKey((int) $request->route('checkin'))->firstOrFail()->delete();

        return $this->toTracker($map);
    }

    private function toTracker(ImpactMap $map): RedirectResponse
    {
        return redirect()->to(route('impact-maps.show', $this->params($map)).'#track');
    }
}
