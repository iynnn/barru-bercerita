<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command(
    'bps:pull data --vars=81,34 --start=100 --end=125 --domain=7310 --out=public/hasil_semua_var.json'
)->dailyAt('01:30');
