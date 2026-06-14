<?php

// Domain constants ported from the original prodmee-slate25.html prototype.
// These mirror STAGES, PSTAGES, CHECKLIST, FORMAT/GENRE/BUDGET lists and the
// Anthropic model + token pricing used for usage cost estimates.

return [
    'stages' => [
        ['id' => 'idea',           'label' => 'Idea',            'color' => '#6b8299'],
        ['id' => 'desarrollo',     'label' => 'Development',     'color' => '#5599dd'],
        ['id' => 'empaquetado',    'label' => 'Packaging',       'color' => '#ccbb44'],
        ['id' => 'financiacion',   'label' => 'Financing',       'color' => '#d98a44'],
        ['id' => 'preproduccion',  'label' => 'Pre-production',  'color' => '#8a86e0'],
        ['id' => 'produccion',     'label' => 'Production',      'color' => '#3fb6c9'],
        ['id' => 'postproduccion', 'label' => 'Post-production', 'color' => '#46c0a0'],
        ['id' => 'delivered',      'label' => 'Delivered',       'color' => '#5fcf6f'],
    ],

    'pitch_statuses' => [
        ['id' => 'preparando',  'label' => 'Preparing',     'color' => '#6b8299'],
        ['id' => 'enviado',     'label' => 'Sent',          'color' => '#5599dd'],
        ['id' => 'revision',    'label' => 'In review',     'color' => '#ccbb44'],
        ['id' => 'negociacion', 'label' => 'Negotiation',   'color' => '#d98a44'],
        ['id' => 'cerrado',     'label' => 'Closed',        'color' => '#4dcc88'],
        ['id' => 'pasado',      'label' => 'Passed / No',   'color' => '#9a5a5a'],
    ],

    'formats' => ['Series', 'Film', 'Vertical', 'Reality', 'Docuseries', 'Docufollow', 'Documentary'],

    'genres' => [
        'Action', 'Adventure', 'Animation', 'Anthology', 'Biopic', 'Comedy', 'Coming of age',
        'Crime', 'Documentary', 'Drama', 'Family', 'Fantasy', 'Historical', 'Horror', 'Musical',
        'Mystery', 'Romance', 'Sci-Fi', 'Sports', 'Suspense', 'Thriller', 'War', 'Western',
    ],

    'budgets' => ['$0–1M', '$1–3M', '$3–5M', '$5–10M', '$10–20M', '$20M+'],

    'checklist' => [
        ['phase' => 'Development', 'items' => [
            ['id' => 'dev_deck', 'label' => 'Deck'],
            ['id' => 'dev_bible', 'label' => 'Bible'],
            ['id' => 'dev_script', 'label' => 'Script / pilot'],
            ['id' => 'dev_rights', 'label' => 'Rights / option / chain of title'],
            ['id' => 'dev_pkg', 'label' => 'Packaging (showrunner/director + talent)'],
        ]],
        ['phase' => 'Financing', 'items' => [
            ['id' => 'fin_topsheet', 'label' => 'Preliminary budget (top-sheet)'],
            ['id' => 'fin_budget', 'label' => 'Detailed budget / line budget'],
            ['id' => 'fin_plan', 'label' => 'Financing plan (incentives, co-pro)'],
            ['id' => 'fin_deal', 'label' => 'Platform / network deal'],
            ['id' => 'fin_green', 'label' => 'Greenlight'],
        ]],
        ['phase' => 'Pre-production', 'items' => [
            ['id' => 'pre_cast', 'label' => 'Cast'],
            ['id' => 'pre_crew', 'label' => 'Crew + HODs'],
            ['id' => 'pre_loc', 'label' => 'Locations + permits'],
            ['id' => 'pre_equip', 'label' => 'Equipment'],
            ['id' => 'pre_sched', 'label' => 'Breakdown & shooting schedule'],
            ['id' => 'pre_legal', 'label' => 'Contracts / clearances / E&O'],
        ]],
        ['phase' => 'Production', 'items' => [
            ['id' => 'prod_shoot', 'label' => 'Principal photography'],
            ['id' => 'prod_dailies', 'label' => 'Dailies / continuity'],
        ]],
        ['phase' => 'Post-production', 'items' => [
            ['id' => 'post_edit', 'label' => 'Editing'],
            ['id' => 'post_sound', 'label' => 'Sound & mix'],
            ['id' => 'post_music', 'label' => 'Music / score'],
            ['id' => 'post_color', 'label' => 'Color'],
            ['id' => 'post_vfx', 'label' => 'VFX / titles'],
            ['id' => 'post_deliver', 'label' => 'QC & deliverables / localization'],
            ['id' => 'post_mkt', 'label' => 'Marketing (key art, trailer)'],
        ]],
        ['phase' => 'Delivery', 'items' => [
            ['id' => 'del_screen', 'label' => 'Screening'],
            ['id' => 'del_dist', 'label' => 'Distribution / festivals / release'],
        ]],
    ],

    'ai' => [
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'max_tokens' => 1000,
        // USD per 1M tokens, used for cost_estimate on usage_events.
        'pricing' => [
            'input_per_mtok' => env('ANTHROPIC_INPUT_PRICE', 3.0),
            'output_per_mtok' => env('ANTHROPIC_OUTPUT_PRICE', 15.0),
        ],
    ],

    'otp' => [
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        'length' => 6,
    ],
];
