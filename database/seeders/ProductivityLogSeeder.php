<?php

namespace Database\Seeders;

use App\Models\BacklogItem;
use App\Models\TodoItem;
use Illuminate\Database\Seeder;

class ProductivityLogSeeder extends Seeder
{
    public function run(): void
    {
        TodoItem::query()->delete();

        $days = [
            // Week 38
            'luni' => 1,
            'marti' => 2,
            'miercuri' => 3,
            'joi' => 4,
            'vineri' => 5,
            'sambata' => 6,
            'duminica' => 7,
        ];

        $items = [
            // ---- WEEK 38 ----
            [38, 'luni', 'TUDIA - the rest of tasklist wk36', true],
            [38, 'luni', 'meet with Bharti at 5PM', true],
            [38, 'luni', 'vibe-code an app for links and daily milestones', false],

            [38, 'marti', 'VHE - social media carousel for water part 2', false],
            [38, 'marti', 'TUDIA - tasklist for prognoze', false, 'https://impactfultech.online/s/tudia/p/tasklist-for-prognoze-nvNmCR7wWn'],

            [38, 'joi', 'VHE - TASK: fininsh tree of life', false],
            [38, 'joi', '↳ make amendments', true],
            [38, 'joi', '↳ push to live and invoice', false],
            [38, 'joi', 'vibe-code milestones app', false],
            [38, 'joi', 'goals, payment queues, budgets pages in cashflow-viewer', false],
            [38, 'joi', 'admin stuff', false],
            [38, 'joi', 'TUDIA - prognoze tasklist', false, 'https://impactfultech.online/s/tudia/p/tasklist-for-prognoze-nvNmCR7wWn'],
            [38, 'joi', 'COSMIC - 3rd party content embedder prototype', false],
            [38, 'joi', 'SAM - meet (on book project for schools)', true],

            [38, 'vineri', 'vibe-code milestones app', false],
            [38, 'vineri', 'TUDIA - prognoze tasklist', false, 'https://impactfultech.online/s/tudia/p/tasklist-for-prognoze-nvNmCR7wWn'],

            // ---- WEEK 37 ----
            [37, 'luni', 'digi - renew cartela', true],
            [37, 'luni', 'eon - trimite autocitire', true],
            [37, 'luni', 'update cashflow viewer', true],
            [37, 'luni', 'TUDIA - tasklist wk36', true, 'https://impactfultech.online/s/tudia/p/tasklist-wk36-X6r3xugPM2'],
            [37, 'luni', '↳ scadente si sold / zile (erp db viewer)', true],
            [37, 'luni', '↳ obiective agent (data sync version) - verifica ca merge', true],
            [37, 'luni', '↳ obiective agent - transfera target-uri vechi de pe legacy version', true],
            [37, 'luni', '↳ obiective agent - fa pagina responsive', true],
            [37, 'luni', '↳ obiective agent - copy target-uri de luna trecuta', true],
            [37, 'luni', 'schedule meet w/ Bharti', true],
            [37, 'luni', 'VHE - animate wolves', true],

            [37, 'marti', 'VHE - add "donate" button on header', true],

            [37, 'sambata', 'COSMIC - put prototype online (and send to Bharti on Whatsapp)', true],

            // ---- WEEK 36 ----
            [36, 'miercuri', 'VHE - PRD AND CLICKABLE DEMO', true],
            [36, 'joi', 'cumpara bilet pentru drum catre Suceava', true],
            [36, 'sambata', 'REECH - DEV', true],
            [36, 'sambata', 'VHE - Higgsfield play around - create drafts for stuff related to water canva content', true],
            [36, 'sambata', 'Miriam @ VHE - create meeting notes and have meeting', true],

            // ---- WEEK 35 ----
            [35, 'vineri', 'TUDIA - IMPORTA DATELE PENTRU OBIECTIVE AGENTI AUGUST', true],
            [35, 'vineri', 'MAKE PRODUCTS - REVIEW ARTICOLE', true],
            [35, 'vineri', 'RESPOND TO BHARTI EMAIL (GMAIL)', true],
            [35, 'marti', 'Tudia - make checks for sold pe zile si incasari pe zile', true],

            // ---- WEEK 34 ----
            [34, 'luni', 'VHE new project dev', true],
            [34, 'luni', 'update Miriam - send demo link', true],
            [34, 'miercuri', 'Saints Nearby mobile app QA', true],
            [34, 'joi', 'saints nearby - send new APK', true],
            [34, 'joi', 'cashflow viewer - update', true],
            [34, 'joi', 'cashflow viewer - add in active projects w Milestones, Invoices and Payments', true],
            [34, 'joi', 'send invoice for Saints Nearby to Sirpod IT - 250 GBP', true],
            [34, 'vineri', 'reinstall Windows', true],

            // ---- WEEK 33 ----
            [33, 'luni', "Tudia platform dev - 'data sync'", true, 'https://impactfultech.online/s/tudia/p/t8-data-sync-ZDeyqEq3xq'],
            [33, 'marti', 'Tudia: add preturi referinta (start with Prunar Florin)', true],
            [33, 'marti', 'update Tudor', true],
            [33, 'marti', 'create AI Tips & Tricks', true],
            [33, 'miercuri', 'Tudia: add preturi referinta (Ciocea Mirela)', true],
            [33, 'miercuri', 'cashflow scoreboard', true],
            [33, 'joi', 'fix your unarranged overdraft in Lloyds bank account', true],
            [33, 'joi', 'create /backup-db skill for Cursor (for my projects - mainly Laravel and React Native)', true],
            [33, 'joi', 'Tudia: add preturi referinta (T8 - Data Sync)', true],
            [33, 'joi', 'Europass CV pt Roro', true],
            [33, 'vineri', 'Tudia: update Tudor (erp viewer new report: solduri si incasari clienti)', true],

            // ---- WEEK 32 ----
            [32, 'luni', 'cashflow viewer dev', true],
            [32, 'luni', 'summariser dev', true],
            [32, 'marti', 'i-am scris lui Terry (prospectul de la Sam, pentru un small job)', true],
            [32, 'marti', 'send Obiective agenti to Tudor', true],
            [32, 'marti', 'updates in Saints Nearby platform: am adaugat noile tari', true],
            [32, 'marti', 'updates in Saints Nearby platform: am adaugat featurul "Activitate" cu buton undo', true],
            [32, 'marti', 'updates in Saints Nearby platform: am implementat colour-coded text pt iteme care au descriere', true],
            [32, 'marti', 'updates in Saints Nearby platform: i-am updatat pe Alin si Dan in group chat', true],
            [32, 'miercuri', 'PRD dev cu Miriam', true],
            [32, 'joi', 'created Reech app', true],
            [32, 'vineri', 'cashflow viewer app dev', true],
            [32, 'vineri', 'summariser app dev', true],
            [32, 'vineri', 'follow up Terry small gig', true],
            [32, 'vineri', 'neo-wars dev', true],

            // ---- WEEK 31 ----
            [31, 'marti', 'catchup cu Tudor', true],
            [31, 'marti', 'summariser', true],
            [31, 'marti', 'cash app', true],
            [31, 'miercuri', 'Tudia Platform - am introdus produsele de pe erp db', true],
            [31, 'miercuri', 'cash app dev', true],
            [31, 'vineri', 'TUDIA - download July sales data', true],
            [31, 'vineri', 'TUDIA - update Tudia platform with July sales data', true],
            [31, 'vineri', 'TUDIA - get Obiective agents results for July', true],
            [31, 'vineri', 'write saints nearby PRD', true],
        ];

        $positionByDate = [];

        foreach ($items as $row) {
            [$week, $dayName, $title, $done] = $row;
            $url = $row[4] ?? null;

            $date = now()
                ->setISODate(2026, $week, $days[$dayName])
                ->toDateString();

            $positionByDate[$date] = ($positionByDate[$date] ?? 0) + 1;

            TodoItem::query()->create([
                'date' => $date,
                'title' => $title,
                'url' => $url,
                'is_completed' => $done,
                'position' => $positionByDate[$date],
            ]);
        }

        $leftovers = [
            'saints nearby mobile app release',
            "Tudia - T8 [on 'dev' env]",
            'suna la SLC',
        ];

        $max = (int) BacklogItem::query()->max('position');

        foreach ($leftovers as $title) {
            if (BacklogItem::query()->where('title', $title)->exists()) {
                continue;
            }

            $max++;

            BacklogItem::query()->create([
                'title' => $title,
                'is_completed' => false,
                'is_separator' => false,
                'position' => $max,
            ]);
        }
    }
}
