<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\V1\Controllers\MyEarningsController;

// The teacher app: my own pay.
Route::middleware(['auth:sanctum', 'can:payroll.view-own'])->get('teacher/earnings', [MyEarningsController::class, 'show'])->name('teacher.earnings');
