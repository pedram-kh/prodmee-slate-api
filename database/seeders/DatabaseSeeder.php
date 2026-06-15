<?php

namespace Database\Seeders;

use App\Models\Buyer;
use App\Models\Comment;
use App\Models\Pitch;
use App\Models\Project;
use App\Models\ProjectLink;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $guillaume = User::create([
            'name' => 'Guillaume de Fonvielle',
            'email' => 'guillaume.defonvielle@prodmee.com',
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $projects = [
            ['The Invisible Border', 'Series · Drama', 'Political thriller', 'desarrollo', 'interno', '$5–10M',
                'A Mexican prosecutor discovers that the disappearance of her brother is tied to a cross-border network reaching into her own family.',
                'A Mexican prosecutor pulls one thread and unravels a cross-border conspiracy that runs through her own blood.'],
            ['North Coast', 'Film · Feature', 'Family drama', 'empaquetado', 'externo', '$3–5M',
                'Three siblings return to their childhood home to sell it and confront the secret that drove them apart.', ''],
            ['Requiem for a Dancer', 'Series · Limited', 'Biopic / Drama', 'idea', 'externo', '$10–20M',
                'The rise and fall of a tango dancer in 1930s Buenos Aires.', ''],
            ['The Last Summer', 'Film · Feature', 'Coming of age', 'financiacion', 'interno', '$1–3M',
                'In a coastal town, two teenagers share a summer that changes everything before they part ways.', ''],
            ['Knot', 'Series · Drama', 'Suspense', 'produccion', 'externo', '$5–10M',
                'A group of strangers is trapped after an accident and must decide how far they will go to survive.', ''],
        ];

        $projRef = [];
        foreach ($projects as $i => [$title, $format, $genre, $stage, $origin, $tier, $logline, $tagline]) {
            $project = Project::create(compact('title', 'format', 'genre', 'stage', 'origin', 'tier', 'logline', 'tagline'));
            $projRef['p' . ($i + 1)] = $project;
            $project->users()->attach($guillaume->id, ['relation' => 'member']);
        }

        // Extra one-pager detail + links + a comment on the flagship project.
        $p1 = $projRef['p1'];
        $p1->update([
            'concept' => 'A taut serialized thriller: prosecutor Elena Marin searches for her missing brother and is dragged into a trafficking network protected at the highest levels.',
            'why_now' => 'Latin-American led prestige thrillers travel globally; audiences are hungry for grounded cross-border crime drama with real stakes.',
            'references_text' => 'ZeroZeroZero, The Bridge, Narcos',
            'participants' => 'Showrunner TBD · Lead role: open offer · MX/ES co-production',
            'language' => 'Spanish',
            'episodes' => "8 × 50'",
            'territory' => 'MX · US Hispanic · global',
            'packaging' => 'Casting By — casting & talent packaging · Itaca — development, writers room, financing & sales',
            'notes' => 'Potential MX/ES co-production. Looking for a showrunner.',
            'share_token' => 'shr_demo0001',
        ]);
        ProjectLink::create(['project_id' => $p1->id, 'label' => 'Reference reel', 'url' => 'https://vimeo.com']);
        ProjectLink::create(['project_id' => $p1->id, 'label' => 'Series bible', 'url' => 'https://drive.google.com']);
        Comment::create([
            'project_id' => $p1->id,
            'user_id' => $guillaume->id,
            'author_name' => $guillaume->name,
            'body' => 'Strong logline — we should lock a showrunner before taking this to buyers.',
        ]);

        $buyers = [
            ['Netflix', 'Paola Mendoza', 'Dir. Content LatAm', 'Latin America'],
            ['Prime Video', 'Carlos Bing', 'Originals LatAm', 'Latin America'],
            ['Max', 'Ana Restrepo', 'Acquisitions', 'Latin America'],
            ['ViX', 'Rodrigo Salas', 'Scripted Content', 'US Hispanic / MX'],
            ['Disney+ / Star+', 'Marta Lillo', 'Originals', 'LatAm'],
            ['Movistar Plus+', 'Javier Olmo', 'Fiction', 'Spain'],
            ['Apple TV+', 'Dana Wu', 'Intl. Content', 'Global'],
        ];
        $buyerRef = [];
        foreach ($buyers as $i => [$platform, $contact, $role, $territory]) {
            $buyerRef['b' . ($i + 1)] = Buyer::create(compact('platform', 'contact', 'role', 'territory'));
        }

        $pitches = [
            ['p4', 'b1', 'negociacion', '2026-05-20', 'Awaiting formal offer after meeting with the director.'],
            ['p4', 'b3', 'pasado', '2026-04-30', 'Passed: does not fit their film slate this year.'],
            ['p1', 'b1', 'enviado', '2026-05-28', 'Sent one-sheet + bible. Follow up in one week.'],
            ['p1', 'b2', 'preparando', null, 'Preparing teaser deck for Prime.'],
            ['p2', 'b6', 'revision', '2026-05-22', 'Under review by the Movistar+ fiction team.'],
            ['p5', 'b4', 'cerrado', '2026-03-15', 'Closed with ViX. Contract signed.'],
            ['p3', 'b7', 'enviado', '2026-05-31', 'Apple showed initial interest in the cast.'],
        ];
        foreach ($pitches as [$pk, $bk, $status, $last, $next]) {
            Pitch::create([
                'project_id' => $projRef[$pk]->id,
                'buyer_id' => $buyerRef[$bk]->id,
                'status' => $status,
                'last_contact' => $last,
                'next' => $next,
            ]);
        }
    }
}
