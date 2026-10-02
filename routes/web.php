<?php

use App\Http\Controllers\TranscriptController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TranscriptController::class, 'index'])->name('transcripts.index');
Route::get('/transcripts/{transcript}', [TranscriptController::class, 'show'])->name('transcripts.show');

// Each of these spends Fire credit, so they are rate-limited per IP (see AppServiceProvider).
Route::middleware('throttle:fire')->group(function () {
    Route::post('/transcripts', [TranscriptController::class, 'store'])->name('transcripts.store');
    Route::post('/transcripts/{transcript}/analyze', [TranscriptController::class, 'analyze'])->name('transcripts.analyze');
});
