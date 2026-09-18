<?php

namespace Database\Seeders;

use App\Models\Link;
use App\Models\LinkGroup;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PartnersSeeder extends Seeder
{
    /**
     * Seed the partners hub with known projects and links.
     *
     * Replaces existing partners projects only (does not touch assets / todos).
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            Project::query()->where('hub', 'partners')->each(function (Project $project): void {
                $project->delete();
            });

            foreach ($this->projects() as $index => $projectData) {
                $project = Project::query()->create([
                    'hub' => 'partners',
                    'name' => $projectData['name'],
                    'slug' => Project::uniqueSlug('partners', $projectData['name']),
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
                'name' => 'Tudia',
                'groups' => [
                    [
                        'name' => 'Apps',
                        'links' => [
                            [
                                'title' => 'Tudia Platform - PROD',
                                'url' => 'https://tudia.impactfultech.space/',
                                'note' => 'currently on legacy - needs updating...',
                            ],
                            [
                                'title' => 'Tudia Platform - DEV (w/ Data Sync)',
                                'url' => 'https://tudia-dev.impactfultech.space/',
                            ],
                            [
                                'title' => 'Tudia Platform - DEV (Legacy)',
                                'url' => 'https://tudia-dev-3.impactfultech.space',
                            ],
                            [
                                'title' => 'Tudia ERP DB Viewer',
                                'url' => 'https://tudia-erp-db-viewer.impactfultech.space/',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Docs',
                        'links' => [
                            [
                                'title' => '🔶 PRD',
                                'url' => 'https://impactfultech.online/s/tudia',
                            ],
                            [
                                'title' => '🔷 Tech Docs',
                                'url' => 'https://bookstack.impactfultech.com/shelves/tudia',
                            ],
                            [
                                'title' => 'Initial Requirement Doc',
                                'url' => 'https://docs.google.com/spreadsheets/d/18EtqhOqj1z6TiIwHJOxdaK43I25rDSVSDyJ9WJ-SEjU/edit?gid=0#gid=0',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Saints Nearby',
                'groups' => [
                    [
                        'name' => 'Apps',
                        'links' => [
                            [
                                'title' => 'Admin Dashboard (web portal)',
                                'url' => 'https://saints-nearby.impactfultech.online/',
                            ],
                            [
                                'title' => 'Mobile App Download Link - (Google Play - Internal Testers)',
                                'url' => 'https://play.google.com/apps/internaltest/4701527991610622794',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Docs',
                        'links' => [
                            [
                                'title' => '🔷 Tech Docs',
                                'url' => 'https://bookstack.impactfultech.com/shelves/saints-nearby',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Tools',
                        'links' => [
                            [
                                'title' => 'Google Play Console',
                                'url' => 'https://play.google.com/console/u/1/developers',
                                'note' => 'on SirPod org account',
                            ],
                            [
                                'title' => 'PostHog',
                                'url' => 'https://eu.posthog.com/login',
                                'note' => 'Account: saintsnearby+iustin@impactfultech.com',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Vibrant Health Edu',
                'groups' => [
                    [
                        'name' => 'Apps',
                        'links' => [
                            [
                                'title' => 'Web Site - PROD',
                                'url' => 'https://vheducation.org/',
                            ],
                            [
                                'title' => 'Web Site - DEV',
                                'url' => 'https://vhe.impactfultech.online/',
                            ],
                            [
                                'title' => 'Education Platform - DEV',
                                'url' => 'http://vheplatform.impactfultech.website/',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Tools',
                        'links' => [
                            [
                                'title' => 'email',
                                'url' => '#',
                                'note' => 'TO ADD',
                            ],
                            [
                                'title' => 'PostHog',
                                'url' => '#',
                                'note' => 'TO ADD',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'La Bârlog',
                'groups' => [
                    [
                        'name' => 'Marketing / Sites',
                        'links' => [
                            [
                                'title' => 'Landing Page - PROD',
                                'url' => 'http://labarlog.ro/',
                            ],
                            [
                                'title' => 'Landing Page - DEV',
                                'url' => 'https://barlog-dev.impactfultech.online/',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Tools',
                        'links' => [
                            [
                                'title' => 'Jira Team Board',
                                'url' => 'https://barlogdev.atlassian.net/jira/software/projects/KAN/list?jql=project%20%3D%20KAN%20ORDER%20BY%20cf%5B10019%5D%20ASC',
                            ],
                            [
                                'title' => 'Github Repo (Landing Page)',
                                'url' => 'https://github.com/Activit123/barlog-loyalty-frontend',
                            ],
                            [
                                'title' => 'PostHog',
                                'url' => 'https://eu.posthog.com/project/201226/home',
                                'note' => 'Account: iusting20@gmail.com',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Docs',
                        'links' => [
                            [
                                'title' => 'CRO Docs',
                                'url' => 'https://drive.google.com/drive/folders/1bzfPtQixoNfHvyibHruNl6wksMbRqK2j?usp=drive_link',
                                'note' => 'TO MOVE IN BOOKSTACK APP',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Hacking Health (Soraya)',
                'is_archived' => true,
                'groups' => [
                    [
                        'name' => 'Apps',
                        'links' => [
                            [
                                'title' => 'Web App - PRODUCTION',
                                'url' => 'https://hackinghealth-web-816795331147.europe-west3.run.app/',
                                'note' => 'also soraya.health',
                            ],
                            [
                                'title' => 'Web App - PRODUCTION',
                                'url' => 'https://hackingcancer.impactfultech.space/',
                                'note' => 'outdated (ask Waqas if its fine to remove this)',
                            ],
                            [
                                'title' => 'Web App - DEV (outdated)',
                                'url' => 'https://hackingcancer-dev.impactfultech.space/',
                                'note' => 'outdated (ask Waqas if its fine to remove this)',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Docs',
                        'links' => [
                            [
                                'title' => 'API Docs (Swagger)',
                                'url' => 'https://flowise-fastapi-service-v28-5fy2dptasa-ey.a.run.app/docs',
                            ],
                            [
                                'title' => 'Requirements Doc',
                                'url' => 'https://docs.google.com/spreadsheets/d/1JcTaZwvGzLigesy5bqmHsCY5e7T-cAjOCyDkH8qDfjY/edit?gid=2097839109#gid=2097839109',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Marketing / Sites',
                        'links' => [
                            [
                                'title' => 'Landing Page',
                                'url' => 'https://www.hackingcancer.health/',
                            ],
                            [
                                'title' => 'More links',
                                'url' => '#',
                                'note' => 'ADD IN THE REST OF THE LINKS...',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Goana',
                'groups' => [],
            ],
            [
                'name' => 'Football Betting App (cu Alex Pal)',
                'groups' => [],
            ],
        ];
    }
}
