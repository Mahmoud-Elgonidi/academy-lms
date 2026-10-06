<?php

use Illuminate\Support\Facades\Route;
use Modules\Classroom\Http\Controllers\LessonJoinController;

// The apps: a ticket into a lesson's room.
Route::middleware('auth:sanctum')->post('lessons/{session}/join', LessonJoinController::class)->name('lessons.join');
