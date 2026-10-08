<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\V1\Controllers\TicketsController;

// The family and teacher apps: writing to the academy and following the answers (the same as "Help" in /my and "Support" in /teach).
Route::middleware('auth:sanctum')->prefix('support')->group(function () {
    Route::get('new', [TicketsController::class, 'options'])->name('support.new');
    Route::get('tickets', [TicketsController::class, 'index'])->name('support.tickets');
    Route::post('tickets', [TicketsController::class, 'store'])->middleware(['idempotent', 'throttle:10,1'])->name('support.tickets.store');
    Route::get('tickets/{ticket}', [TicketsController::class, 'show'])->name('support.tickets.show');
    Route::post('tickets/{ticket}/messages', [TicketsController::class, 'reply'])->middleware(['idempotent', 'throttle:30,1'])->name('support.tickets.reply');
    Route::post('tickets/{ticket}/reopen', [TicketsController::class, 'reopen'])->middleware('throttle:20,1')->name('support.tickets.reopen');
    Route::post('tickets/{ticket}/close', [TicketsController::class, 'close'])->middleware('throttle:20,1')->name('support.tickets.close');
    Route::get('tickets/{ticket}/messages/{message}/file', [TicketsController::class, 'file'])->name('support.tickets.file');
});
