<?php

namespace App\Livewire;

use App\Models\OnboardingState;
use App\Support\Onboarding;
use App\Support\OnboardingCatalog;
use Livewire\Attributes\On;
use Livewire\Component;

class OnboardingDock extends Component
{
    public bool $open = false;

    public bool $confirmingSkip = false;

    /** @var list<string> */
    public array $expanded = [];

    public function toggleOpen(): void
    {
        if ($this->open) {
            $this->close();

            return;
        }

        $this->confirmingSkip = false;
        $this->open = true;

        $active = OnboardingCatalog::activeGroupKey();
        $this->expanded = $active === null ? [] : [$active];
    }

    public function close(): void
    {
        if ($this->confirmingSkip) {
            $this->confirmingSkip = false;

            return;
        }

        $this->open = false;
    }

    public function toggleGroup(string $key): void
    {
        if (in_array($key, $this->expanded, true)) {
            $this->expanded = array_values(array_filter(
                $this->expanded,
                fn (string $existing): bool => $existing !== $key,
            ));

            return;
        }

        $this->expanded[] = $key;
    }

    public function toggleStep(string $key): void
    {
        Onboarding::toggle($key);
    }

    public function resetTour(): void
    {
        Onboarding::reset();
    }

    public function askSkip(): void
    {
        $this->confirmingSkip = true;
    }

    public function skip(): void
    {
        Onboarding::skip();
        $this->confirmingSkip = false;
        $this->open = false;
    }

    #[On('onboarding-updated')]
    public function refreshProgress(): void
    {
        //
    }

    public function render()
    {
        $state = OnboardingState::current();
        $groups = OnboardingCatalog::groups();
        $completed = $state->completed ?? [];
        $total = 0;
        $done = 0;

        foreach ($groups as $group) {
            foreach ($group['steps'] as $step) {
                $total++;

                if (in_array($step['key'], $completed, true)) {
                    $done++;
                }
            }
        }

        $radius = 15;
        $circumference = 2 * M_PI * $radius;
        $offset = $total === 0 ? $circumference : $circumference * (1 - ($done / $total));

        return view('livewire.onboarding-dock', [
            'groups' => $groups,
            'completed' => $completed,
            'done' => $done,
            'total' => $total,
            'circumference' => $circumference,
            'offset' => $offset,
            'activeGroup' => OnboardingCatalog::activeGroupKey(),
            'dismissed' => $state->dismissed_at !== null,
        ]);
    }
}
