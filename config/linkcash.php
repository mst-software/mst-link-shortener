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
        'captcha_provider' => 'turnstile',
        'visit_mode' => 'auto',
        'code_session_seconds' => 600,
        'widget_attempts_per_ip_per_day' => 5,
        'tutorial_video_url' => '',
        'advertiser_default_price_micro' => 10_000,
        'advertiser_min_topup_micro' => 5_000_000,
        'vnd_per_usd' => 25_000,
        'sepay_bank_name' => '',
        'sepay_account_number' => '',
        'sepay_account_name' => '',
    ],

];
