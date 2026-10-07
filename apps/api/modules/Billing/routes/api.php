<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\V1\Controllers\FamilyBillingController;

// The family app: lessons left, the invoices this person pays, paying one online.
Route::middleware('auth:sanctum')->prefix('family')->group(function () {
    Route::get('balance', [FamilyBillingController::class, 'balance'])->name('family.balance');
    Route::get('invoices', [FamilyBillingController::class, 'invoices'])->name('family.invoices');
    Route::post('invoices/{invoice}/checkout', [FamilyBillingController::class, 'checkout'])->middleware('idempotent')->name('family.invoices.checkout');
});
