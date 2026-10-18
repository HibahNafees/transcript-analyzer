<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TranscriptController;
use Illuminate\Support\Facades\Route;

// Stripe calls this server-to-server; it is authenticated by the Stripe-Signature header, not a session.
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:auth');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:auth');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', [TranscriptController::class, 'index'])->name('transcripts.index');
    Route::get('/transcripts/{transcript}', [TranscriptController::class, 'show'])->name('transcripts.show');

    // Each of these spends Fire credit, so they are rate-limited per account (see AppServiceProvider).
    Route::middleware('throttle:fire')->group(function () {
        Route::post('/transcripts', [TranscriptController::class, 'store'])->name('transcripts.store');
        Route::post('/transcripts/{transcript}/analyze', [TranscriptController::class, 'analyze'])->name('transcripts.analyze');
    });
});
