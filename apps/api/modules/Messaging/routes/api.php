<?php

use Illuminate\Support\Facades\Route;
use Modules\Messaging\Http\V1\Controllers\ConversationsController;

// The family and teacher apps: talking with a teacher or a student on the platform (the same as "Messages" in /my and /teach).
Route::middleware('auth:sanctum')->prefix('messages')->group(function () {
    Route::get('conversations', [ConversationsController::class, 'index'])->name('messages.conversations');
    Route::get('unread', [ConversationsController::class, 'unread'])->name('messages.unread');
    Route::post('conversations', [ConversationsController::class, 'store'])->middleware('throttle:30,1')->name('messages.conversations.store');
    Route::get('conversations/{conversation}', [ConversationsController::class, 'show'])->name('messages.conversations.show');
    Route::post('conversations/{conversation}/messages', [ConversationsController::class, 'send'])->middleware(['idempotent', 'throttle:30,1'])->name('messages.conversations.send');
});
