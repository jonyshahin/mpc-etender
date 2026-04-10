<?php

use App\Models\Tender;
use App\Services\TenderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-close tenders at submission deadline
Schedule::call(function () {
    Tender::where('status', 'published')
        ->where('submission_deadline', '<=', now())
        ->each(fn ($t) => app(TenderService::class)->closeSubmission($t));
})->everyMinute()->name('close-expired-tenders');
