<?php

namespace App\Livewire;

use App\Models\JobApplication;
use App\Support\JobApplicationWriter;
use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Opportunity list. Filters here do not re-render the rest of the gigs page. */
class JobOpportunityList extends Component
{
    /** Category owned by the parent page (charts and next steps follow it). */
    #[Reactive]
    public string $category = 'all';

    #[Url(as: 'closed', history: true, except: 'all')]
    public string $closedFilter = 'all';

    #[Url(as: 'channel', history: true, except: 'all')]
    public string $channel = 'all';

    public function mount(): void
    {
        $this->normalize();
    }

    public function updatedClosedFilter(): void
    {
        $this->normalize();
    }

    public function updatedChannel(): void
    {
        $this->normalize();
    }

    public function render()
    {
        $this->normalize();

        $apps = JobApplication::query()->whereNull('removed_at')
            ->when($this->category !== 'all', fn ($q) => $q->where('category', $this->category))
            ->withCount('messages')
            ->withExists(['messages as has_reply' => fn ($q) => $q->where('direction', 'received')])
            ->get()
            ->sortBy([
                fn ($a) => $a->sheet_row === null ? 1 : 0,
                fn ($a) => $a->sheet_row ?? PHP_INT_MAX,
                fn ($a) => $a->id,
            ])->values();

        $listed = $this->applyFilters($apps);

        return view('livewire.job-opportunity-list', [
            'listed' => $listed,
            'openCount' => $apps->reject(fn ($a) => $a->isClosed())->count(),
            'appliedCount' => $apps->filter(fn ($a) => JobApplications::isAppliedStatus($a->status))->count(),
            'categories' => JobApplicationWriter::CATEGORY_LABELS,
        ]);
    }

    private function applyFilters(Collection $apps): Collection
    {
        $listed = match ($this->closedFilter) {
            'open' => $apps->reject(fn ($a) => $a->isClosed())->values(),
            'applied' => $apps->filter(fn ($a) => JobApplications::isAppliedStatus($a->status))->values(),
            'discussion' => $apps->filter(fn ($a) => $a->isInDiscussion())->values(),
            'closed' => $apps->filter(fn ($a) => $a->isClosed())->values(),
            default => $apps->sortBy([
                fn ($a) => $a->isClosed() ? 1 : 0,
                fn ($a) => $a->sheet_row ?? PHP_INT_MAX,
                fn ($a) => $a->id,
            ])->values(),
        };

        if ($this->channel !== 'all') {
            $listed = $listed->filter(fn ($a) => $a->channel() === $this->channel)->values();
        }

        return $listed;
    }

    private function normalize(): void
    {
        if (! in_array($this->closedFilter, ['all', 'open', 'applied', 'discussion', 'closed'], true)) {
            $this->closedFilter = 'all';
        }
        if (! in_array($this->channel, ['all', 'direct', 'agency', 'upwork'], true)) {
            $this->channel = 'all';
        }
    }
}
