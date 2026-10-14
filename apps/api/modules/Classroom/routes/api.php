<?php

use Illuminate\Support\Facades\Route;
use Modules\Classroom\Http\Controllers\LessonJoinController;
use Modules\Classroom\Http\V1\MeetingLinkController;
use Modules\Classroom\Http\V1\RecordingsController;

// The apps: a ticket into a lesson's room.
Route::middleware('auth:sanctum')->post('lessons/{session}/join', LessonJoinController::class)->name('lessons.join');

// The teacher's own Zoom or Meet link (lessons on outside links).
Route::middleware(['auth:sanctum', 'can:reports.write'])->prefix('teacher/meeting-link')->group(function () {
    Route::get('/', [MeetingLinkController::class, 'show'])->name('teacher.meeting-link');
    Route::put('/', [MeetingLinkController::class, 'update'])->middleware('throttle:20,1')->name('teacher.meeting-link.save');
});

// Lesson recordings: consent, the recordings to watch, and starting and stopping one in a lesson.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('recordings/consents', [RecordingsController::class, 'consents'])->name('recordings.consents');
    Route::post('recordings/consents/{person}', [RecordingsController::class, 'agree'])->whereUuid('person')->name('recordings.consents.agree');
    Route::post('recordings/consents/{person}/withdraw', [RecordingsController::class, 'withdraw'])->whereUuid('person')->name('recordings.consents.withdraw');
    Route::get('family/recordings', [RecordingsController::class, 'family'])->name('family.recordings');
    Route::get('teacher/recordings', [RecordingsController::class, 'teacher'])->middleware('can:reports.write')->name('teacher.recordings');
    Route::post('recordings/{recording}/watch', [RecordingsController::class, 'watch'])->whereUuid('recording')->name('recordings.watch');
    Route::post('lessons/{session}/recording', [RecordingsController::class, 'start'])->name('lessons.recording.start');
    Route::post('lessons/{session}/recording/stop', [RecordingsController::class, 'stop'])->name('lessons.recording.stop');
});
