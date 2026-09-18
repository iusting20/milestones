<?php

namespace Database\Seeders;

use App\Models\Link;
use App\Models\LinkGroup;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetsSeeder extends Seeder
{
    /**
     * Seed the assets hub with known projects and links.
     *
     * Replaces existing assets projects only (does not touch partners / todos).
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            Project::query()->where('hub', 'assets')->each(function (Project $project): void {
                $project->delete();
            });

            foreach ($this->projects() as $index => $projectData) {
                $project = Project::query()->create([
                    'hub' => 'assets',
                    'name' => $projectData['name'],
                    'slug' => Project::uniqueSlug('assets', $projectData['name']),
                    'position' => $index + 1,
                    'is_archived' => $projectData['is_archived'] ?? false,
                ]);

                foreach ($projectData['groups'] as $groupIndex => $groupData) {
                    $group = LinkGroup::query()->create([
                        'project_id' => $project->id,
                        'name' => $groupData['name'],
                        'position' => $groupIndex + 1,
                    ]);

                    foreach ($groupData['links'] as $linkIndex => $linkData) {
                        Link::query()->create([
                            'link_group_id' => $group->id,
                            'title' => $linkData['title'],
                            'url' => $linkData['url'],
                            'note' => $linkData['note'] ?? null,
                            'position' => $linkIndex + 1,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * @return list<array{name: string, is_archived?: bool, groups: list<array{name: string, links: list<array{title: string, url: string, note?: string}>}>}>
     */
    private function projects(): array
    {
        return [
            [
                'name' => 'Impactful Tech',
                'groups' => [
                    [
                        'name' => 'Marketing / Sites',
                        'links' => [
                            [
                                'title' => 'Landing Page',
                                'url' => 'http://impactfultech.com/',
                            ],
                            [
                                'title' => 'Make Products',
                                'url' => 'https://makeproducts.substack.com/',
                                'note' => 'Substack',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Tools',
                        'links' => [
                            [
                                'title' => 'email',
                                'url' => 'https://mail.zoho.eu/zm/#mail/views/unread',
                                'note' => 'Account: iustin@impactfultech.com',
                            ],
                            [
                                'title' => 'email marketing creation (email octopus)',
                                'url' => '#',
                                'note' => 'TO ADD',
                            ],
                            [
                                'title' => 'PostHog',
                                'url' => 'https://us.posthog.com/login',
                                'note' => 'Account: iustin@impactfultech.com',
                            ],
                            [
                                'title' => 'UberSuggest (SEO Tool)',
                                'url' => 'https://app.neilpatel.com/en/login',
                                'note' => 'Account: iusting20@gmail.com',
                            ],
                            [
                                'title' => 'upwork prospect list',
                                'url' => '#',
                                'note' => 'TO ADD',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Creation Tools',
                        'links' => [
                            [
                                'title' => 'Laravel plugin directory',
                                'url' => 'https://laravel-package-ocean.com/',
                            ],
                            [
                                'title' => 'React Native plugin directory',
                                'url' => 'https://reactnative.directory/',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'The Job Lobby',
                'groups' => [
                    [
                        'name' => 'Apps',
                        'links' => [
                            [
                                'title' => 'Web App - DEV',
                                'url' => 'https://tjl.impactfultech.online/dashboard',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Marketing / Sites',
                        'links' => [
                            [
                                'title' => 'Landing Page',
                                'url' => 'https://landing-tjl.impactfultech.online/',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Docs',
                        'links' => [
                            [
                                'title' => '🔶 PRD',
                                'url' => '#',
                                'note' => 'TO CREATE...',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Volexi (ArchViz)',
                'groups' => [
                    [
                        'name' => 'Apps',
                        'links' => [
                            [
                                'title' => 'Web App - PROD',
                                'url' => 'https://www.volexi.com/editor',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Marketing / Sites',
                        'links' => [
                            [
                                'title' => 'Landing Page',
                                'url' => 'https://www.volexi.com/',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Volexi (AEO)',
                'groups' => [
                    [
                        'name' => 'Marketing / Sites',
                        'links' => [
                            [
                                'title' => 'Landing Page',
                                'url' => 'https://www.volexi.ai/',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Summariser',
                'groups' => [
                    [
                        'name' => 'Apps',
                        'links' => [
                            [
                                'title' => 'Web App - DEV',
                                'url' => 'https://summariser.impactfultech.online/dashboard',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Docs',
                        'links' => [
                            [
                                'title' => '🔷 Tech Docs',
                                'url' => 'https://bookstack.impactfultech.com/shelves/summariser',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Neo Wars',
                'groups' => [
                    [
                        'name' => 'Apps',
                        'links' => [
                            [
                                'title' => 'Web App - DEV',
                                'url' => 'https://neo-wars.impactfultech.online/play',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Marketing / Sites',
                        'links' => [
                            [
                                'title' => 'Landing Page',
                                'url' => 'https://neo-wars.impactfultech.online/',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Radiologie Concept App',
                'groups' => [
                    [
                        'name' => 'Docs',
                        'links' => [
                            [
                                'title' => '🔶 PRD',
                                'url' => '#',
                                'note' => 'TO CREATE...',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'BookMeBCN',
                'groups' => [],
            ],
        ];
    }
}
