<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class OnboardingCatalog
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     purpose: string,
     *     routes: list<string>,
     *     steps: list<array{key: string, title: string, purpose: string, route: string, parameters: array<string, string>}>
     * }>
     */
    public static function groups(): array
    {
        $groups = [
            [
                'key' => 'milestones',
                'label' => 'Milestones',
                'purpose' => 'The working list. Start and Finish write a timed session.',
                'routes' => ['milestones', 'milestones.drip-matrix', 'milestones.log', 'backlog', 'backlog.drip-matrix'],
                'steps' => [
                    ['key' => 'milestone.create', 'title' => 'Add a task on today', 'purpose' => 'A milestone is one piece of work for a day.', 'route' => 'milestones', 'parameters' => []],
                    ['key' => 'milestone.start', 'title' => 'Press Start', 'purpose' => 'Starts the clock on that task.', 'route' => 'milestones', 'parameters' => []],
                    ['key' => 'milestone.finish', 'title' => 'Press Finish', 'purpose' => 'Stops the clock and writes the session.', 'route' => 'milestones', 'parameters' => []],
                    ['key' => 'milestone.drip', 'title' => 'Tag it DRIP', 'purpose' => 'D, R, I, P is money times energy: Delegation, Replacement, Investment, Production.', 'route' => 'milestones', 'parameters' => []],
                    ['key' => 'milestone.matrix', 'title' => 'Open the DRIP Matrix', 'purpose' => 'The same tasks, split into four quadrants.', 'route' => 'milestones.drip-matrix', 'parameters' => []],
                    ['key' => 'milestone.log', 'title' => 'Open the Log', 'purpose' => 'The session you finished, and which projects got the time.', 'route' => 'milestones.log', 'parameters' => []],
                    ['key' => 'milestone.week', 'title' => 'Add a week-level task', 'purpose' => 'Work that belongs to the week, not one day.', 'route' => 'milestones', 'parameters' => []],
                    ['key' => 'milestone.backlog', 'title' => 'Add a backlog item', 'purpose' => 'A parking lot you pull onto a week later.', 'route' => 'backlog', 'parameters' => []],
                ],
            ],
            [
                'key' => 'week-board',
                'label' => 'Week Board',
                'purpose' => 'Client work for the week: Planned, WIP, Done.',
                'routes' => ['week-board'],
                'steps' => [
                    ['key' => 'weekboard.card', 'title' => 'Add a Planned card', 'purpose' => 'A card is one piece of client work for this week.', 'route' => 'week-board', 'parameters' => []],
                    ['key' => 'weekboard.start', 'title' => 'Start it', 'purpose' => 'Moves the card to WIP and starts a milestone.', 'route' => 'week-board', 'parameters' => []],
                    ['key' => 'weekboard.done', 'title' => 'Move it to Done', 'purpose' => 'The week board is done when the card is done.', 'route' => 'week-board', 'parameters' => []],
                ],
            ],
            [
                'key' => 'timelines',
                'label' => 'Timelines',
                'purpose' => 'Longer work with a start and a target finish, across months.',
                'routes' => ['timelines'],
                'steps' => [
                    ['key' => 'timeline.create', 'title' => 'Create an item', 'purpose' => 'A timeline item is work that spans days, not a daily task.', 'route' => 'timelines', 'parameters' => []],
                    ['key' => 'timeline.finish', 'title' => 'Set a finish date', 'purpose' => 'The day you intend to be done.', 'route' => 'timelines', 'parameters' => []],
                    ['key' => 'timeline.complete', 'title' => 'Mark it complete', 'purpose' => 'Records the day it actually finished.', 'route' => 'timelines', 'parameters' => []],
                ],
            ],
            [
                'key' => 'comms',
                'label' => 'Comms',
                'purpose' => 'When you last touched a client, an asset project, or a person.',
                'routes' => ['comms'],
                'steps' => [
                    ['key' => 'comms.entry', 'title' => 'Log one entry', 'purpose' => 'A quick update, or a review and planning meeting.', 'route' => 'comms', 'parameters' => []],
                ],
            ],
            [
                'key' => 'links',
                'label' => 'Links',
                'purpose' => 'Bookmarks per project. Partners and Assets are two hubs.',
                'routes' => ['links.hub', 'links.*'],
                'steps' => [
                    ['key' => 'links.project', 'title' => 'Create a project on Partners', 'purpose' => 'A project is a drawer of links for one partner.', 'route' => 'links.hub', 'parameters' => ['hub' => 'partners']],
                    ['key' => 'links.link', 'title' => 'Add one link', 'purpose' => 'A link is a URL you want one click away.', 'route' => 'links.hub', 'parameters' => ['hub' => 'partners']],
                    ['key' => 'links.assets', 'title' => 'Open Assets', 'purpose' => 'The same drawer, for work you own rather than client work.', 'route' => 'links.hub', 'parameters' => ['hub' => 'assets']],
                ],
            ],
            [
                'key' => 'read',
                'label' => 'Read',
                'purpose' => 'Timed reading. Books and articles are separate. Sessions roll up to Stats.',
                'routes' => ['read', 'read.articles', 'read.stats'],
                'steps' => [
                    ['key' => 'read.book', 'title' => 'Add a book', 'purpose' => 'A book you are reading, separate from articles.', 'route' => 'read', 'parameters' => []],
                    ['key' => 'read.start', 'title' => 'Start a session', 'purpose' => 'Starts the reading clock.', 'route' => 'read', 'parameters' => []],
                    ['key' => 'read.stop', 'title' => 'Stop it', 'purpose' => 'Writes the minutes to today and this week.', 'route' => 'read', 'parameters' => []],
                    ['key' => 'read.stats', 'title' => 'Open Stats', 'purpose' => 'Where those sessions add up.', 'route' => 'read.stats', 'parameters' => []],
                    ['key' => 'read.article', 'title' => 'Add an article', 'purpose' => 'Articles live on their own list, with the same timer.', 'route' => 'read.articles', 'parameters' => []],
                ],
            ],
        ];

        if (self::gigsAvailable()) {
            $groups[] = [
                'key' => 'gigs',
                'label' => 'Gigs',
                'purpose' => 'A pipeline of roles: status, weekly pace, follow-ups, cover letters. Unlinked on purpose.',
                'routes' => ['jobs.secret'],
                'steps' => [
                    ['key' => 'gigs.create', 'title' => 'Add an application', 'purpose' => 'One role you are tracking.', 'route' => 'jobs.secret', 'parameters' => ['token' => (string) config('jobs.page_token')]],
                    ['key' => 'gigs.view', 'title' => 'Open one', 'purpose' => 'The role, its status, and the notes on it.', 'route' => 'jobs.secret', 'parameters' => ['token' => (string) config('jobs.page_token')]],
                    ['key' => 'gigs.letters', 'title' => 'Open My cover letters', 'purpose' => 'Letters you can reuse across applications.', 'route' => 'jobs.secret', 'parameters' => ['token' => (string) config('jobs.page_token')]],
                ],
            ];
        }

        return $groups;
    }

    public static function has(string $key): bool
    {
        foreach (self::groups() as $group) {
            foreach ($group['steps'] as $step) {
                if ($step['key'] === $key) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function activeGroupKey(): ?string
    {
        foreach (self::groups() as $group) {
            foreach ($group['routes'] as $name) {
                if (request()->routeIs($name)) {
                    return $group['key'];
                }
            }
        }

        return null;
    }

    public static function gigsAvailable(): bool
    {
        return filled(config('jobs.page_token')) && Route::has('jobs.secret');
    }
}
