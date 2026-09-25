<?php

use Goldnead\Accounts\Http\Controllers\Web\AccountController;
use Goldnead\Accounts\Http\Controllers\Web\LinkController;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;

/*
 * Action routes: served under `/!/statamic-accounts/…`, named
 * `statamic.accounts.…`, with the `web` middleware group.
 *
 * The GET routes are the links from the mails. Each is signed and expires;
 * the controller checks the rest (hash of the address, request still open).
 * The POST routes are the forms rendered by the `accounts:*` tags and need a
 * signed-in user.
 */

Route::name('accounts.')->group(function () {
    Route::get('verify/{user}/{hash}', [LinkController::class, 'verify'])
        ->middleware([ValidateSignature::class, 'throttle:6,1'])
        ->name('verify');

    Route::get('email/confirm/{request}/{hash}', [LinkController::class, 'confirmEmail'])
        ->whereNumber('request')
        ->middleware([ValidateSignature::class, 'throttle:6,1'])
        ->name('email.confirm');

    Route::get('deletion/cancel/{request}', [LinkController::class, 'cancelDeletion'])
        ->whereNumber('request')
        ->middleware([ValidateSignature::class, 'throttle:6,1'])
        ->name('deletion.cancel');

    Route::post('verification/resend', [AccountController::class, 'resendVerification'])
        ->middleware('throttle:6,1')
        ->name('verification.resend');

    Route::post('email', [AccountController::class, 'changeEmail'])
        ->middleware('throttle:6,1')
        ->name('email.change');

    Route::post('email/cancel', [AccountController::class, 'cancelEmailChange'])
        ->name('email.cancel');

    Route::post('deletion', [AccountController::class, 'requestDeletion'])
        ->middleware('throttle:6,1')
        ->name('deletion.request');

    Route::post('deletion/withdraw', [AccountController::class, 'withdrawDeletion'])
        ->name('deletion.withdraw');

    // Limited by `accounts.export.throttle` ("max,minutes", per person),
    // through the named limiter the provider defines. Named, so the value is
    // read at request time and a change in the settings layer applies.
    Route::get('export', [AccountController::class, 'export'])
        ->middleware('throttle:accounts-export')
        ->name('export');
});
