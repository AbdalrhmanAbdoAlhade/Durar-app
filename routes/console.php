<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Runs every minute: flips scheduled auctions to active at starts_at,
// and closes active auctions (setting the winner) once ends_at passes.
Schedule::command('auctions:process')->everyMinute()->withoutOverlapping();
