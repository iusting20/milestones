<?php

namespace Tests\Feature;

use App\Livewire\DayList;
use App\Livewire\OnboardingDock;
use App\Livewire\TaskLogBoard;
use App\Models\OnboardingState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_button_shows_progress_and_checks_can_be_reset(): void
    {
        $this->get(route('milestones'))
            ->assertOk()
            ->assertSee('aria-label="Onboarding, 0 of 26"', false);

        Livewire::test(OnboardingDock::class)
            ->call('toggleOpen')
            ->assertSet('open', true)
            ->call('toggleStep', 'milestone.create')
            ->assertSee('1/26');

        $this->assertContains('milestone.create', OnboardingState::current()->completed);

        $this->get(route('milestones'))
            ->assertSee('aria-label="Onboarding, 1 of 26"', false);

        Livewire::test(OnboardingDock::class)
            ->call('resetTour');

        $this->assertSame([], OnboardingState::current()->completed);
    }

    public function test_skip_dismisses_until_the_button_is_opened_again(): void
    {
        Livewire::test(OnboardingDock::class)
            ->call('toggleOpen')
            ->call('askSkip')
            ->assertSet('confirmingSkip', true)
            ->assertSee('Skip hides this list')
            ->call('skip')
            ->assertSet('open', false)
            ->assertSet('confirmingSkip', false);

        $this->assertNotNull(OnboardingState::current()->dismissed_at);

        Livewire::test(OnboardingDock::class)
            ->call('resetTour');

        $this->assertNotNull(OnboardingState::current()->dismissed_at);

        Livewire::test(OnboardingDock::class)
            ->call('toggleOpen')
            ->assertSet('open', true);

        $this->assertNull(OnboardingState::current()->dismissed_at);
    }

    public function test_creating_a_day_task_marks_the_tour_step(): void
    {
        Livewire::test(DayList::class, [
            'date' => now()->toDateString(),
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])->set('newTitle', 'Tour task')->call('addItem');

        $this->assertContains('milestone.create', OnboardingState::current()->completed);
    }

    public function test_opening_the_log_marks_the_visit_step(): void
    {
        Livewire::test(TaskLogBoard::class);

        $this->assertContains('milestone.log', OnboardingState::current()->completed);
    }

    public function test_gigs_group_is_hidden_without_a_page_token(): void
    {
        config(['jobs.page_token' => null]);

        Livewire::test(OnboardingDock::class)
            ->call('toggleOpen')
            ->call('toggleGroup', 'milestones')
            ->assertDontSee('Gigs');
    }
}
