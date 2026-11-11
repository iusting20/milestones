<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobApplicationEvent;
use App\Support\JobApplicationWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON API for the job-applications tracker (the milestones page is the only tracker). */
class JobApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $withEvents = $this->wantsEvents($request);
        $query = JobApplication::query()->orderByRaw('sheet_row is null')->orderBy('sheet_row')->orderBy('id');
        if (! $request->boolean('include_removed')) {
            $query->whereNull('removed_at');
        }
        if ($request->filled('status')) {
            $query->whereRaw('lower(status) = ?', [mb_strtolower((string) $request->query('status'))]);
        }
        if ($request->filled('category')) {
            $category = JobApplicationWriter::canonicalCategory((string) $request->query('category'));
            if ($category) {
                $query->where('category', $category);
            } else {
                $query->whereRaw('0 = 1');
            }
        }
        if ($withEvents) {
            $query->with(['events' => fn ($q) => $q->orderBy('occurred_at')->orderBy('id')]);
        }
        $apps = $query->get();

        return response()->json([
            'data' => $apps->map(fn ($a) => self::present($a, $withEvents))->values(),
            'meta' => ['count' => $apps->count()],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $application = JobApplication::query()->findOrFail($id);
        $application->load(['events' => fn ($q) => $q->orderBy('occurred_at')->orderBy('id')]);

        return response()->json(['data' => self::present($application, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        [$app, $events] = JobApplicationWriter::create($this->payload($request), 'api');

        return response()->json([
            'data' => self::present($app->fresh()),
            'events' => array_map(fn ($e) => self::presentEvent($e), $events),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $application = JobApplication::query()->findOrFail($id);
        $events = JobApplicationWriter::update($application, $this->payload($request), 'api');

        return response()->json([
            'data' => self::present($application->fresh()),
            'events' => array_map(fn ($e) => self::presentEvent($e), $events),
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $data = $request->json()->all() ?: $request->request->all();

        return is_array($data) ? $data : [];
    }

    private function wantsEvents(Request $request): bool
    {
        return in_array('events', array_map('trim', explode(',', (string) $request->query('with', ''))), true);
    }

    /** @return array<string, mixed> */
    public static function present(JobApplication $a, bool $withEvents = false): array
    {
        $out = ['id' => $a->id] + JobApplicationWriter::values($a) + [
            'origin' => $a->origin,
            'removed_at' => $a->removed_at?->toIso8601ZuluString(),
            'created_at' => $a->created_at?->toIso8601ZuluString(),
            'updated_at' => $a->updated_at?->toIso8601ZuluString(),
        ];
        if ($withEvents) {
            $out['events'] = $a->events->map(fn ($e) => self::presentEvent($e))->values()->all();
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function presentEvent(JobApplicationEvent $e): array
    {
        return [
            'id' => $e->id,
            'type' => $e->type,
            'from' => $e->from_value,
            'to' => $e->to_value,
            'note' => $e->note,
            'source' => $e->source,
            'occurred_at' => $e->occurred_at?->copy()->utc()->toIso8601ZuluString(),
        ];
    }
}
