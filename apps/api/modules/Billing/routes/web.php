<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\DemoGatewayController;
use Modules\Billing\Http\Controllers\PayController;

// Paying an invoice on the academy's own host, without signing in: the link in the invoice message,
// sending proof of paying by hand, the way back from a gateway's page, and the demo gateway's pretend page. Every address is signed.
Route::middleware(['web', 'tenant'])->prefix('pay')->group(function () {
    Route::get('invoice/{invoice}', [PayController::class, 'show'])->middleware(['signed:relative', 'throttle:links'])->name('billing.pay');
    Route::get('invoice/{invoice}/document', [PayController::class, 'document'])->middleware(['signed:relative', 'throttle:links'])->name('billing.pay.document');
    Route::post('invoice/{invoice}/proof', [PayController::class, 'proof'])->middleware(['signed:relative', 'throttle:6,1'])->name('billing.pay.proof');
    Route::post('invoice/{invoice}/{account}', [PayController::class, 'start'])->middleware(['signed:relative', 'throttle:12,1'])->name('billing.pay.start');
    Route::get('back/{checkout}', [PayController::class, 'back'])->middleware(['signed:relative', 'throttle:links'])->name('billing.pay.return');
    Route::get('demo/{ref}', [DemoGatewayController::class, 'show'])->middleware(['signed:relative', 'throttle:links'])->name('billing.demo-gateway');
    Route::post('demo/{ref}', [DemoGatewayController::class, 'decide'])->middleware(['signed:relative', 'throttle:12,1'])->name('billing.demo-gateway.decide');
});
