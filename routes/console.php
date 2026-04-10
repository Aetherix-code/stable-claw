<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Secretary heartbeat — runs every 5 minutes to check for pending tasks
Schedule::command('secretary:heartbeat')->everyFiveMinutes();
