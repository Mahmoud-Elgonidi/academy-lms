<?php

use Illuminate\Support\Facades\Route;
use Modules\Learning\Http\V1\Controllers\LearnerController;
use Modules\Learning\Http\V1\Controllers\TeacherReportsController;

// The teacher (mobile app): my students, reports to write (starting from today's plan), and homework to mark.
Route::middleware(['auth:sanctum', 'can:reports.write'])->prefix('teacher')->group(function () {
    Route::get('students', [TeacherReportsController::class, 'students'])->name('teacher.students');
    Route::get('reports', [TeacherReportsController::class, 'index'])->name('teacher.reports.index');
    Route::get('reports/{report}', [TeacherReportsController::class, 'show'])->name('teacher.reports.show');
    Route::put('reports/{report}', [TeacherReportsController::class, 'submit'])->name('teacher.reports.submit');
    Route::get('homework', [TeacherReportsController::class, 'homework'])->name('teacher.homework.index');
    Route::post('homework/{homework}/review', [TeacherReportsController::class, 'mark'])->name('teacher.homework.review');
});

// Students and guardians: the roadmap, the reports and the homework of each child.
Route::middleware('auth:sanctum')->prefix('learning')->group(function () {
    Route::get('students', [LearnerController::class, 'students'])->name('learning.students');
    Route::get('journey', [LearnerController::class, 'journey'])->name('learning.journey');
    Route::get('enrollments/{enrollment}/roadmap', [LearnerController::class, 'roadmap'])->name('learning.roadmap');
    Route::get('enrollments/{enrollment}/reports', [LearnerController::class, 'reports'])->name('learning.reports');
    Route::get('homework', [LearnerController::class, 'homework'])->name('learning.homework');
    Route::post('homework/{homework}/submissions', [LearnerController::class, 'handIn'])->name('learning.homework.submit');
    Route::get('submissions/{submission}/file', [LearnerController::class, 'file'])->name('learning.submissions.file');
});
