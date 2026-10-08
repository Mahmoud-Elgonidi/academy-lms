<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\V1\Controllers\MyEarningsController;

// The teacher app: my own pay (when the academy's plan includes payroll).
Route::middleware(['auth:sanctum', 'plan.feature:payroll', 'can:payroll.view-own'])->get('teacher/earnings', [MyEarningsController::class, 'show'])->name('teacher.earnings');
