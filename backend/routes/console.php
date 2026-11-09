<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Inventory;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refill services use a daily station allocation. Reset every inventory record
// to the standard 100-unit stock at the start of each day.
Schedule::call(function (): void {
    Inventory::query()->update(['quantity_on_hand' => 100]);
})->dailyAt('00:00')->name('renew-refill-service-stock')->withoutOverlapping();
