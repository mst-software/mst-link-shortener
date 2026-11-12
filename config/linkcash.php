<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default settings
    |--------------------------------------------------------------------------
    |
    | Fallback values used by App\Services\Settings when the settings table has
    | no row for a key. Money values are stored in micro-USD (1 USD = 1,000,000).
    |
    */

    'defaults' => [
        'dwell_seconds' => 10,
        'unique_window_hours' => 24,
        'review_days' => 3,
        'min_withdraw_micro' => 5_000_000,
        'referral_percent' => 12,
        'session_minutes' => 30,
        'view_purge_days' => 60,
        'alias_length' => 6,
    ],

];
