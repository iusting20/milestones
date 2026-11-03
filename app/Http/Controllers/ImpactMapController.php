<?php

namespace App\Http\Controllers;

use App\Models\ImpactMap;
use App\Models\ImpactMapNode;
use App\Support\ImpactMapLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ImpactMapController extends Controller
{
    public function index(): View
    {
        return view('impact-maps.index', [
            'maps' => ImpactMap::query()->withCount('nodes')
                ->orderByRaw("COALESCE(client, '') = ''")->orderBy('client')->orderByDesc('updated_at')->get()
                ->groupBy(fn (ImpactMap $m) => (string) $m->client),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'client' => 'nullable|string|max:255']);
        $client = trim((string) ($data['client'] ?? '')) ?: null;

        $map = DB::transaction(function () use ($data, $client) {
            $map = ImpactMap::query()->create(['name' => trim($data['name']), 'client' => $client]);
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
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $map = $this->map($request);
        $data = $request->validate(['name' => 'required|string|max:255', 'client' => 'nullable|string|max:255']);
        $client = trim((string) ($data['client'] ?? '')) ?: null;
        $map->update(['name' => trim($data['name']), 'client' => $client]);

        return redirect()->route('impact-maps.show', $this->params($map));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->map($request)->delete();

        return redirect()->route('impact-maps.index', ['token' => config('impact_maps.token')]);
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
        return ImpactMap::query()->findOrFail((int) $request->route('map'));
    }

    private function node(Request $request, ImpactMap $map): ImpactMapNode
    {
        return $map->nodes()->findOrFail((int) $request->route('node'));
    }

    /** @return array<string, mixed> */
    private function params(ImpactMap $map): array
    {
        return ['token' => config('impact_maps.token'), 'map' => $map->id];
    }
}
