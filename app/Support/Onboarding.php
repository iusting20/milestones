<?php

namespace App\Support;

use App\Models\OnboardingState;
use Livewire\Component;

class Onboarding
{
    public static function complete(string $key): void
    {
        if (! OnboardingCatalog::has($key)) {
            return;
        }

        $state = OnboardingState::current();
        $completed = $state->completed ?? [];

        if (in_array($key, $completed, true)) {
            return;
        }

        $completed[] = $key;
        $state->completed = array_values($completed);
        $state->save();

        self::notify();
    }

    public static function toggle(string $key): void
    {
        if (! OnboardingCatalog::has($key)) {
            return;
        }

        $state = OnboardingState::current();
        $completed = $state->completed ?? [];

        if (in_array($key, $completed, true)) {
            $completed = array_values(array_filter(
                $completed,
                fn (string $existing): bool => $existing !== $key,
            ));
        } else {
            $completed[] = $key;
        }

        $state->completed = array_values($completed);
        $state->save();
    }

    public static function reset(): void
    {
        $state = OnboardingState::current();
        $state->completed = [];
        $state->save();
    }

    public static function skip(): void
    {
        $state = OnboardingState::current();
        $state->dismissed_at = now();
        $state->save();
    }

    public static function reopen(): void
    {
        $state = OnboardingState::current();

        if ($state->dismissed_at === null) {
            return;
        }

        $state->dismissed_at = null;
        $state->save();
    }

    private static function notify(): void
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 12) as $frame) {
            $object = $frame['object'] ?? null;

            if ($object instanceof Component) {
                $object->dispatch('onboarding-updated');

                return;
            }
        }
    }
}
