<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule AI Help Center article embedding sync
use Illuminate\Support\Facades\Schedule;

Schedule::command('helpcenter:sync')->hourly()->withoutOverlapping();
