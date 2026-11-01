<?php

use Illuminate\Support\Facades\Route;
use Modules\Quran\Http\Web\CertificatePageController;
use Modules\Quran\Http\Web\ClassMushafController;

// The shared mushaf beside the room on a lesson's class page (the academy's own host; a web session or the signed link).
Route::middleware(['web', 'tenant', 'throttle:links', 'plan.feature:quran'])->prefix('class/{session}')->group(function () {
    Route::get('mushaf', [ClassMushafController::class, 'show'])->middleware('throttle:240,1')->name('quran.class.mushaf');
    Route::post('mushaf', [ClassMushafController::class, 'change'])->middleware('throttle:120,1')->name('quran.class.mushaf.change');
});

// A certificate's page to read and print, by its signed link (the family's message, the portal and the app).
Route::middleware(['web', 'tenant', 'plan.feature:quran'])->group(function () {
    Route::get('quran/certificates/{certificate}', [CertificatePageController::class, 'show'])->middleware(['signed:relative', 'throttle:links'])->name('quran.certificate');
});
