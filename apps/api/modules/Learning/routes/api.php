<?php

use Illuminate\Support\Facades\Route;
use Modules\Learning\Http\V1\Controllers\ChallengesController;
use Modules\Learning\Http\V1\Controllers\HifzController;
use Modules\Learning\Http\V1\Controllers\JourneyController;
use Modules\Learning\Http\V1\Controllers\LearnerController;
use Modules\Learning\Http\V1\Controllers\MushafController;
use Modules\Learning\Http\V1\Controllers\TeacherHifzController;
use Modules\Learning\Http\V1\Controllers\TeacherReportsController;

// The teacher (mobile app): my students, reports to write (starting from today's plan), and homework to mark.
Route::middleware(['auth:sanctum', 'can:reports.write'])->prefix('teacher')->group(function () {
    Route::get('students', [TeacherReportsController::class, 'students'])->name('teacher.students');
    Route::get('reports', [TeacherReportsController::class, 'index'])->name('teacher.reports.index');
    Route::get('reports/{report}', [TeacherReportsController::class, 'show'])->name('teacher.reports.show');
    Route::put('reports/{report}', [TeacherReportsController::class, 'submit'])->name('teacher.reports.submit');
    // A draft from the teacher's voice note (the academy's AI helper): heard, deleted, nothing saved.
    Route::post('reports/{report}/draft', [TeacherReportsController::class, 'draft'])->middleware(['plan.feature:ai', 'throttle:20,60'])->name('teacher.reports.draft');
    Route::get('homework', [TeacherReportsController::class, 'homework'])->name('teacher.homework.index');
    Route::post('homework/{homework}/review', [TeacherReportsController::class, 'mark'])->name('teacher.homework.review');
    // The Quran's surahs, to choose what homework asks to recite.
    Route::get('texts/quran', [TeacherReportsController::class, 'surahs'])->name('teacher.texts.quran');
    // A student's hifz map: where each page stands, the last reviews, and what the student already knows.
    Route::get('students/{enrollment}/hifz', [TeacherHifzController::class, 'show'])->name('teacher.hifz.show');
    Route::post('students/{enrollment}/hifz/memorized', [TeacherHifzController::class, 'mark'])->middleware('throttle:30,1')->name('teacher.hifz.mark');
    Route::post('students/{enrollment}/hifz/clear', [TeacherHifzController::class, 'clear'])->middleware('throttle:30,1')->name('teacher.hifz.clear');
    // A student's mistakes notebook: the words marked in their lessons' shared mushaf.
    Route::get('students/{enrollment}/mistakes', [TeacherHifzController::class, 'mistakes'])->name('teacher.mistakes');
});

// A Quran lesson's shared mushaf: its teacher turns the pages and marks words; its student and their guardians follow.
Route::middleware('auth:sanctum')->prefix('learning/lessons/{session}')->group(function () {
    Route::get('mushaf', [MushafController::class, 'show'])->middleware('throttle:240,1')->name('learning.mushaf');
    Route::put('mushaf', [MushafController::class, 'turn'])->middleware('throttle:120,1')->name('learning.mushaf.turn');
    Route::post('mistakes', [MushafController::class, 'mark'])->middleware('throttle:120,1')->name('learning.mistakes.mark');
    Route::delete('mistakes', [MushafController::class, 'unmark'])->middleware('throttle:120,1')->name('learning.mistakes.unmark');
});

// Students and guardians: the roadmap, the reports and the homework of each child.
Route::middleware('auth:sanctum')->prefix('learning')->group(function () {
    Route::get('students', [LearnerController::class, 'students'])->name('learning.students');
    Route::get('journey', [LearnerController::class, 'journey'])->name('learning.journey');
    // The journey: the curriculum as islands and stations, the missions, the stars and the week's chest.
    Route::get('journey/map', [JourneyController::class, 'map'])->middleware('throttle:60,1')->name('learning.journey.map');
    Route::post('journey/chest', [JourneyController::class, 'chest'])->middleware(['idempotent', 'throttle:10,1'])->name('learning.journey.chest');
    Route::get('enrollments/{enrollment}/roadmap', [LearnerController::class, 'roadmap'])->name('learning.roadmap');
    Route::get('enrollments/{enrollment}/reports', [LearnerController::class, 'reports'])->name('learning.reports');
    Route::get('homework', [LearnerController::class, 'homework'])->name('learning.homework');
    Route::post('homework/{homework}/submissions', [LearnerController::class, 'handIn'])->name('learning.homework.submit');
    Route::get('submissions/{submission}/file', [LearnerController::class, 'file'])->name('learning.submissions.file');

    // The hifz map and its daily review: each page's strength, today's pages, reciting a page or saying how it went.
    Route::get('hifz', [HifzController::class, 'show'])->middleware('throttle:60,1')->name('learning.hifz');
    Route::get('hifz/pages/{page}', [HifzController::class, 'page'])->whereNumber('page')->middleware('throttle:60,1')->name('learning.hifz.page');
    Route::post('hifz/pages/{page}/recitations', [HifzController::class, 'recite'])->whereNumber('page')->middleware('throttle:10,1')->name('learning.hifz.recite');
    Route::get('hifz/recitations/{recitation}', [HifzController::class, 'recitation'])->middleware('throttle:60,1')->name('learning.hifz.recitation');
    Route::post('hifz/pages/{page}/grade', [HifzController::class, 'grade'])->whereNumber('page')->middleware(['idempotent', 'throttle:30,1'])->name('learning.hifz.grade');
    // The mistakes notebook: the words marked in the learner's lessons, by surah.
    Route::get('mistakes', [MushafController::class, 'book'])->middleware('throttle:60,1')->name('learning.mistakes');

    // The game layer: challenges by stage, collecting rewards, and guardians' cheers.
    Route::get('challenges', [ChallengesController::class, 'index'])->name('learning.challenges');
    Route::post('challenges/claim', [ChallengesController::class, 'claim'])->middleware(['idempotent', 'throttle:20,1'])->name('learning.challenges.claim');
    Route::post('cheers', [ChallengesController::class, 'cheer'])->middleware('throttle:20,1')->name('learning.cheers.send');
    Route::post('cheers/seen', [ChallengesController::class, 'seen'])->middleware('throttle:30,1')->name('learning.cheers.seen');
});
