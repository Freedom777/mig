<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// We have already done with process, when inserted image to table
/*Schedule::command('images:process local ' . config('image.paths.images'))
    ->hourlyAt( '00')
    ->appendOutputTo(storage_path('logs/images_process.log'));*/

Schedule::command('images:thumbnails --width=300 --height=200')
    ->hourlyAt( '10')
    ->appendOutputTo(storage_path('logs/thumbnails_process.log'));

Schedule::command('images:metadatas')
    ->hourlyAt( '20')
    ->appendOutputTo(storage_path('logs/metadatas_process.log'));
