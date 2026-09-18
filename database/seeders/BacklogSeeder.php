<?php

namespace Database\Seeders;

use App\Models\BacklogItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BacklogSeeder extends Seeder
{
    /**
     * Seed backlog checklist items.
     *
     * Replaces existing backlog items only.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            BacklogItem::query()->delete();

            foreach ($this->items() as $index => $item) {
                if ($item === '.') {
                    BacklogItem::query()->create([
                        'title' => null,
                        'is_completed' => false,
                        'is_separator' => true,
                        'position' => $index + 1,
                    ]);

                    continue;
                }

                BacklogItem::query()->create([
                    'title' => $item,
                    'is_completed' => false,
                    'is_separator' => false,
                    'position' => $index + 1,
                ]);
            }
        });
    }

    /**
     * @return list<string>
     */
    private function items(): array
    {
        return [
            'CASHFLOW VIEWER - implement feature: payment queues',
            're-do homepage copywriting (Impactful Tech)',
            'money- model (Alex Hormozi) - design with AI',
            '.',
            'schedule meet w/ Matt',
            '.',
            '(MIGRATE APACHE SITES TO NGINX)',
            'SAINTS NEARBY - COMPILE NEW APP AND SEND TO WHATSAPP GROUP',
            '(make PRDs for all projects (partners and assets))',
            '(test out: making tech doc (on bookstack via mcp) with /understand-anything skill)',
            '(update company address on gov.uk)',
            '(catchup w/ Bear - make PRDs for TJL and Radiologie app concept)',
            '(Upwork gig scouting)',
            '(TUDIA - ACCESS LA SITE (DE LA TUDOR)',
            '(OPTIONAL) (MAKE CONTENT FOR INSTAGRAM FROM PROSPECTING EDI CIORNEA DRUM SCHOOL ANALYTICS AND MARKETING)',
            '(ANDREI GOANA - SET UP SHOPIFY AND DOMAIN)',
            '(LA BARLOG - IMPLEMENT TICKETS AND SUMMON TEAM) (REQUEST A TEAM MEETING)',
        ];
    }
}
