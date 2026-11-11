<?php

use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Middleware\JobsApiToken;
use Illuminate\Support\Facades\Route;

// Jobs tracker API: Bearer JOBS_API_TOKEN, no session/CSRF. Not registered when the token is unset.
if (filled(config('jobs.api_token'))) {
    Route::middleware(['throttle:jobs-api', JobsApiToken::class])
        ->prefix('jobs')
        ->name('api.jobs.')
        ->group(function () {
            Route::get('/', [JobApplicationController::class, 'index'])->name('index');
            Route::post('/', [JobApplicationController::class, 'store'])->name('store');
            Route::get('/{id}', [JobApplicationController::class, 'show'])->whereNumber('id')->name('show');
            Route::patch('/{id}', [JobApplicationController::class, 'update'])->whereNumber('id')->name('update');
        });
}
