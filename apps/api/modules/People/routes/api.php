<?php

use Illuminate\Support\Facades\Route;
use Modules\People\Http\V1\Controllers\FamilyController;

// The family app: whose account the person signed in can open (themselves as a student, their children).
Route::middleware('auth:sanctum')->get('family/learners', [FamilyController::class, 'learners'])->name('family.learners');
