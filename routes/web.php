<?php

use App\Http\Controllers\ImpactMapController;
use App\Http\Controllers\LoginController;
use App\Livewire\ActivityStats;
use App\Livewire\BacklogDripMatrix;
use App\Livewire\DripMatrix;
use App\Livewire\JobApplications;
use App\Livewire\JobCommThread;
use App\Livewire\LinkHub;
use App\Livewire\Read;
use App\Livewire\ReadStats;
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
Route::get('/milestones/stats', ActivityStats::class)->name('milestones.stats');
Route::view('/backlog', 'backlog')->name('backlog');

Route::get('/backlog/drip-matrix', BacklogDripMatrix::class)->name('backlog.drip-matrix');

Route::redirect('/roadmaps', '/timelines', 301);
Route::view('/timelines', 'roadmaps')->name('timelines');

Route::view('/week-board', 'week-board')->name('week-board');

Route::view('/comms', 'comms')->name('comms');

Route::get('/read', Read::class)->name('read');

Route::get('/read/articles', Read::class)->name('read.articles');

Route::get('/read/stats', ReadStats::class)->name('read.stats');

// Secret, unlinked job-applications dashboard: unguessable path (JOBS_PAGE_TOKEN) + the normal login.
if (filled(config('jobs.page_token'))) {
    Route::get('/x/{token}', JobApplications::class)
        ->where('token', preg_quote((string) config('jobs.page_token'), '/'))
        ->name('jobs.secret');
    Route::get('/x/{token}/{application}/threads', JobCommThread::class)
        ->where('token', preg_quote((string) config('jobs.page_token'), '/'))
        ->whereNumber('application')
        ->name('jobs.threads');
}

Route::redirect('/links', '/links/partners');

Route::get('/links/{hub}/{slug?}', LinkHub::class)
    ->whereIn('hub', ['partners', 'assets'])
    ->name('links.hub');

// Impact Mapping tool. List + all edits need the normal login; a map page is public (read-only) via its secret slug.
Route::redirect('/impact-maps-{old}', '/impact-maps')->where('old', '[A-Za-z0-9-]+');
Route::prefix('impact-maps')
    ->where(['map' => '[A-Za-z0-9]{24,64}', 'node' => '[0-9]+', 'checkin' => '[0-9]+', 'secret' => '[A-Za-z0-9]{24,64}'])
    ->name('impact-maps.')
    ->controller(ImpactMapController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/reorder', 'reorder')->name('reorder');
        Route::get('/{map}', 'show')->name('show');
        Route::patch('/{map}', 'update')->name('update');
        Route::delete('/{map}', 'destroy')->name('destroy');
        Route::post('/{map}/nodes/{node}', 'storeNode')->name('nodes.store');
        Route::patch('/{map}/nodes/{node}', 'updateNode')->name('nodes.update');
        Route::delete('/{map}/nodes/{node}', 'destroyNode')->name('nodes.destroy');
        Route::post('/{map}/nodes/{node}/reorder', 'reorderChildren')->name('nodes.reorder');
        Route::get('/client/{secret}', 'clientPage')->name('client');
        Route::post('/client/{secret}/maps', 'storeForClient')->name('client.store');
        Route::put('/{map}/tracker', 'updateTracker')->name('tracker.update');
        Route::delete('/{map}/tracker', 'destroyTracker')->name('tracker.destroy');
        Route::post('/{map}/checkins', 'storeCheckin')->name('checkins.store');
        Route::delete('/{map}/checkins/{checkin}', 'destroyCheckin')->name('checkins.destroy');
    });
