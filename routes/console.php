<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:check-document-expiry')->daily();
Schedule::command('app:check-warranty-expiry')->daily();
Schedule::command('app:check-reminders')->daily();
