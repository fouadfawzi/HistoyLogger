<?php

use FouadFawzi\HistoryLogger\Http\Controllers\HistoryController;
use Illuminate\Support\Facades\Route;

if (! config('history-logger.route.enabled', false)) {
    return;
}

Route::middleware((array) config('history-logger.route.middleware', ['web']))
    ->prefix(trim((string) config('history-logger.route.prefix', 'history-logger'), '/'))
    ->name('history-logger.')
    ->group(static function (): void {
        Route::get('/{modelType}/{modelId}', [HistoryController::class, 'show'])->name('show');
    });
