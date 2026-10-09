<?php

use Illuminate\Support\Facades\Route;
use Modules\People\Http\Controllers\CheckFileController;
use Modules\People\Http\Controllers\ConsentLinkController;

// A guardian's consent on the academy's own host, without signing in: each guardian's own signed link from the request.
Route::middleware(['web', 'tenant'])->prefix('consent')->group(function () {
    Route::get('{student}', [ConsentLinkController::class, 'show'])->middleware(['signed:relative', 'throttle:links'])->name('people.consent');
    Route::post('{student}', [ConsentLinkController::class, 'store'])->middleware(['signed:relative', 'throttle:20,1'])->name('people.consent.store');
});

// A teacher's vetting document: the teacher and the safeguarding leads, signed in on the academy's host.
Route::middleware(['web', 'tenant'])->get('people/checks/{check}/file', CheckFileController::class)->middleware('throttle:links')->name('people.check.file');
