<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily research-cadence sweep: flag connections past their next_review_due.
Schedule::command('research:flag-stale')->dailyAt('06:00');

// Daily skill-provenance drift: bump changed skills + flag connections whose latest
// brief used a superseded skill version. Runs after the cadence sweep.
Schedule::command('skills:detect-updates')->dailyAt('06:15');
