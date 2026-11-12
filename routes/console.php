<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('views:aggregate')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('earnings:release')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('views:purge')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('fraud:scan')->hourly()->withoutOverlapping();
