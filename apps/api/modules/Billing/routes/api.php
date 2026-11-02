<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\V1\Controllers\FamilyBillingController;

// The family app: lessons left (and extra lessons to buy), the invoices this person pays, paying one online or sending proof of paying by hand.
Route::middleware('auth:sanctum')->prefix('family')->group(function () {
    Route::get('balance', [FamilyBillingController::class, 'balance'])->name('family.balance');
    // One course's balance: lessons left, expiry, the next invoice (J5a).
    Route::get('courses/{enrollment}/balance', [FamilyBillingController::class, 'courseBalance'])->middleware('throttle:60,1')->name('family.courses.balance');
    Route::post('enrollments/{enrollment}/extra-lessons', [FamilyBillingController::class, 'extraLessons'])->middleware('idempotent')->name('family.extra-lessons');
    Route::get('invoices', [FamilyBillingController::class, 'invoices'])->name('family.invoices');
    Route::get('invoices/{invoice}', [FamilyBillingController::class, 'show'])->name('family.invoices.show');
    Route::post('invoices/{invoice}/coupon', [FamilyBillingController::class, 'applyCoupon'])->middleware(['idempotent', 'throttle:10,1'])->name('family.invoices.coupon');
    Route::delete('invoices/{invoice}/coupon', [FamilyBillingController::class, 'removeCoupon'])->middleware(['idempotent', 'throttle:10,1'])->name('family.invoices.coupon.remove');
    Route::post('invoices/{invoice}/checkout', [FamilyBillingController::class, 'checkout'])->middleware('idempotent')->name('family.invoices.checkout');
    Route::post('invoices/{invoice}/proofs', [FamilyBillingController::class, 'sendProof'])->middleware(['idempotent', 'throttle:10,1'])->name('family.invoices.proofs');
});
