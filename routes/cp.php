<?php

use Goldnead\Accounts\Http\Controllers\Cp\CustomerController;
use Goldnead\Accounts\Http\Controllers\Cp\WiringController;
use Illuminate\Support\Facades\Route;

/*
 * Every route carries `can:` middleware AND the controller checks again.
 * Impersonation is gated by core's `impersonate users` through the user
 * policy, in the controller, because it depends on the target user.
 */
Route::prefix('accounts')->name('accounts.')->group(function () {
    Route::get('/', [CustomerController::class, 'index'])
        ->name('index')
        ->middleware('can:view accounts');

    Route::get('/wiring', [WiringController::class, 'index'])
        ->name('wiring')
        ->middleware('can:view accounts');

    Route::prefix('customers/{user}')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'show'])
            ->name('show')
            ->middleware('can:view accounts');

        Route::get('/export', [CustomerController::class, 'export'])
            ->name('export')
            ->middleware('can:export account data');

        Route::post('/verification/resend', [CustomerController::class, 'resendVerification'])
            ->name('verification.resend')
            ->middleware('can:manage accounts');

        Route::post('/verification/mark', [CustomerController::class, 'markVerified'])
            ->name('verification.mark')
            ->middleware('can:manage accounts');

        Route::post('/deletion', [CustomerController::class, 'scheduleDeletion'])
            ->name('deletion.schedule')
            ->middleware('can:manage accounts');

        Route::delete('/deletion', [CustomerController::class, 'cancelDeletion'])
            ->name('deletion.cancel')
            ->middleware('can:manage accounts');

        Route::post('/impersonate', [CustomerController::class, 'impersonate'])
            ->name('impersonate')
            ->middleware('can:view accounts');
    });
});
