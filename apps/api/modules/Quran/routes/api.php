<?php

use Illuminate\Support\Facades\Route;
use Modules\Quran\Http\V1\Controllers\HifzController;
use Modules\Quran\Http\V1\Controllers\MushafController;
use Modules\Quran\Http\V1\Controllers\TeacherHifzController;

/*
 * The Quran module's API (docs/architecture/02, section 26). Its paths are
 * the ones the released apps and the portal already call (under learning/
 * and teacher/), now served here; each takes the plan's Quran area first
 * (403 feature-not-in-plan without it). Anything new goes under quran/.
 */

// The teacher: the surahs for homework to recite, a student's hifz map, and their mistakes notebook.
Route::middleware(['auth:sanctum', 'plan.feature:quran', 'can:quran.teach'])->prefix('teacher')->group(function () {
    // The Quran's surahs, to choose what homework asks to recite.
    Route::get('texts/quran', [TeacherHifzController::class, 'surahs'])->name('teacher.texts.quran');
    // A student's hifz map: where each page stands, the last reviews, and what the student already knows.
    Route::get('students/{enrollment}/hifz', [TeacherHifzController::class, 'show'])->name('teacher.hifz.show');
    Route::post('students/{enrollment}/hifz/memorized', [TeacherHifzController::class, 'mark'])->middleware('throttle:30,1')->name('teacher.hifz.mark');
    Route::post('students/{enrollment}/hifz/clear', [TeacherHifzController::class, 'clear'])->middleware('throttle:30,1')->name('teacher.hifz.clear');
    // A student's mistakes notebook: the words marked in their lessons' shared mushaf.
    Route::get('students/{enrollment}/mistakes', [TeacherHifzController::class, 'mistakes'])->name('teacher.mistakes');
});

// A Quran lesson's shared mushaf: its teacher turns the pages and marks words; its student and their guardians follow.
Route::middleware(['auth:sanctum', 'plan.feature:quran'])->prefix('learning/lessons/{session}')->group(function () {
    Route::get('mushaf', [MushafController::class, 'show'])->middleware('throttle:240,1')->name('learning.mushaf');
    Route::put('mushaf', [MushafController::class, 'turn'])->middleware('throttle:120,1')->name('learning.mushaf.turn');
    Route::post('mistakes', [MushafController::class, 'mark'])->middleware('throttle:120,1')->name('learning.mistakes.mark');
    Route::delete('mistakes', [MushafController::class, 'unmark'])->middleware('throttle:120,1')->name('learning.mistakes.unmark');
});

// Students and guardians: the hifz map and its daily review, and the mistakes notebook.
Route::middleware(['auth:sanctum', 'plan.feature:quran'])->prefix('learning')->group(function () {
    // Each page's strength, today's pages, reciting a page or saying how it went.
    Route::get('hifz', [HifzController::class, 'show'])->middleware('throttle:60,1')->name('learning.hifz');
    Route::get('hifz/pages/{page}', [HifzController::class, 'page'])->whereNumber('page')->middleware('throttle:60,1')->name('learning.hifz.page');
    Route::post('hifz/pages/{page}/recitations', [HifzController::class, 'recite'])->whereNumber('page')->middleware('throttle:10,1')->name('learning.hifz.recite');
    Route::get('hifz/recitations/{recitation}', [HifzController::class, 'recitation'])->middleware('throttle:60,1')->name('learning.hifz.recitation');
    Route::post('hifz/pages/{page}/grade', [HifzController::class, 'grade'])->whereNumber('page')->middleware(['idempotent', 'throttle:30,1'])->name('learning.hifz.grade');
    // The words marked in the learner's lessons, by surah.
    Route::get('mistakes', [MushafController::class, 'book'])->middleware('throttle:60,1')->name('learning.mistakes');
});
