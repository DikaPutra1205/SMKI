<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Otomatis generate checklist untuk seluruh satuan kerja pada tanggal 1 tiap bulan pukul 00:00
Schedule::command('smki:generate-monthly-checklist')->monthlyOn(1, '00:00');
