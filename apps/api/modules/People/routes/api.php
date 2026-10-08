<?php

use Illuminate\Support\Facades\Route;
use Modules\People\Http\V1\Controllers\ConsentController;
use Modules\People\Http\V1\Controllers\FamilyController;

// The family app: whose account the person signed in can open (themselves as a student, their children).
Route::middleware('auth:sanctum')->get('family/learners', [FamilyController::class, 'learners'])->name('family.learners');

// A guardian's consent to their children's accounts.
Route::middleware('auth:sanctum')->prefix('family/consents')->group(function () {
    Route::get('/', [ConsentController::class, 'index'])->name('family.consents');
    Route::post('{student}', [ConsentController::class, 'grant'])->middleware('throttle:20,1')->name('family.consents.grant');
    Route::post('{student}/withdraw', [ConsentController::class, 'withdraw'])->middleware('throttle:20,1')->name('family.consents.withdraw');
});
