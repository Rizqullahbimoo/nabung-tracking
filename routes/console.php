<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Weekly savings reminder (PRD F-08) - Sundays 09:00 WIB.
Schedule::command('reminders:weekly-savings')
    ->weekly()->sundays()->at('09:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
