<?php

use App\Http\Controllers\ImpactMapController;
use App\Http\Controllers\LoginController;
use App\Livewire\BacklogDripMatrix;
use App\Livewire\DripMatrix;
use App\Livewire\LinkHub;
use App\Livewire\TaskLogBoard;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::redirect('/', '/milestones');

Route::view('/milestones', 'milestones')->name('milestones');

Route::get('/milestones/drip-matrix', DripMatrix::class)->name('milestones.drip-matrix');

Route::get('/milestones/log', TaskLogBoard::class)->name('milestones.log');
Route::view('/backlog', 'backlog')->name('backlog');

Route::get('/backlog/drip-matrix', BacklogDripMatrix::class)->name('backlog.drip-matrix');

Route::view('/roadmaps', 'roadmaps')->name('roadmaps');

Route::view('/week-board', 'week-board')->name('week-board');

Route::view('/comms', 'comms')->name('comms');

Route::redirect('/links', '/links/partners');

Route::get('/links/{hub}/{slug?}', LinkHub::class)
    ->whereIn('hub', ['partners', 'assets'])
    ->name('links.hub');

// Secret Impact Mapping tool: /impact-maps-{token}. Token is checked in EnsureAuthenticated (404 when wrong).
Route::prefix('impact-maps-{token}')
    ->where(['token' => '[A-Za-z0-9-]{16,}', 'map' => '[0-9]+', 'node' => '[0-9]+'])
    ->name('impact-maps.')
    ->controller(ImpactMapController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{map}', 'show')->name('show');
        Route::patch('/{map}', 'update')->name('update');
        Route::delete('/{map}', 'destroy')->name('destroy');
        Route::post('/{map}/nodes/{node}', 'storeNode')->name('nodes.store');
        Route::patch('/{map}/nodes/{node}', 'updateNode')->name('nodes.update');
        Route::delete('/{map}/nodes/{node}', 'destroyNode')->name('nodes.destroy');
    });
