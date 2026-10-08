<?php

use Illuminate\Support\Facades\Route;
use Modules\Classroom\Http\Controllers\ClassroomPageController;

// The class page and its ticket, on the academy's own host (web session or a signed link).
Route::middleware(['web', 'tenant'])->prefix('class/{session}')->group(function () {
    Route::get('/', [ClassroomPageController::class, 'show'])->name('classroom.page');
    Route::post('ticket', [ClassroomPageController::class, 'ticket'])->name('classroom.ticket');
    Route::get('go', [ClassroomPageController::class, 'go'])->name('classroom.go');
    Route::post('recording', [ClassroomPageController::class, 'startRecording'])->name('classroom.recording.start');
    Route::post('recording/stop', [ClassroomPageController::class, 'stopRecording'])->name('classroom.recording.stop');
});
