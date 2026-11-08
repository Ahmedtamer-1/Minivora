<?php

use App\Modules\Platform\AiFunctions\PlatformFunctions;

// Minivora settings read from .env.

return [

    // Root domain. Stores live at <subdomain>.<domain>; the dashboard at app.<domain> (or the root).
    'domain' => env('APP_DOMAIN', 'localhost'),

    // Port appended to generated store URLs in local development (e.g. 8000). Empty in production.
    'port' => env('APP_PORT_SUFFIX', ''),

    // Scheme used when building store URLs.
    'scheme' => env('APP_SCHEME', 'http'),

    // Names that can never be store addresses. FR-ONB-02
    'reserved_subdomains' => [
        'app', 'api', 'www', 'mail', 'admin', 'status',
        'dashboard', 'help', 'support', 'docs', 'blog', 'static', 'cdn', 'assets', 'minivora',
    ],

    'subdomain' => [
        'min' => 3,
        'max' => 40,
    ],

    'locales' => ['ar', 'en'],

    // Sign-in lock: 5 failures per account + IP lock for 15 minutes. NFR-SEC-02
    'login' => [
        'max_attempts' => 5,
        'decay_seconds' => 15 * 60,
    ],

    // Owner AI questions per store per day (NFR-SCL-02). Used once the AI team ships.
    'ai' => [
        'daily_question_cap' => (int) env('AI_DAILY_QUESTION_CAP', 50),

        // Classes whose #[AiFunction] methods the AI team may call (FR-AI-06). Add one per module.
        'functions' => [
            PlatformFunctions::class,
        ],
    ],
];
