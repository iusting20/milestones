<?php

use App\Http\Controllers\ImpactMapController;
use App\Http\Controllers\LoginController;
use App\Livewire\BacklogDripMatrix;
use App\Livewire\DripMatrix;
use App\Livewire\JobApplications;
use App\Livewire\LinkHub;
use App\Livewire\Read;
use App\Livewire\ReadStats;
use App\Livewire\TaskLogBoard;
use App\Models\Book;
use App\Models\BookHighlight;
use App\Models\ReadingSession;
use App\Support\Clock;
use App\Support\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

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

Route::redirect('/roadmaps', '/timelines', 301);
Route::view('/timelines', 'roadmaps')->name('timelines');

Route::view('/week-board', 'week-board')->name('week-board');

Route::view('/comms', 'comms')->name('comms');

Route::get('/read', Read::class)->name('read');

Route::get('/read/articles', Read::class)->name('read.articles');

Route::get('/read/stats', ReadStats::class)->name('read.stats');

Route::get('/read/highlights/export/{book?}', function (?int $book = null) {
    $query = BookHighlight::query()
        ->with('book')
        ->orderBy('book_id')
        ->orderBy('page_number')
        ->orderBy('id');

    $filename = 'reading-highlights.md';
    if ($book !== null) {
        $bookModel = Book::query()->findOrFail($book);
        $query->where('book_id', $bookModel->id);
        $filename = Str::slug($bookModel->title).'-highlights.md';
    }

    $grouped = $query->get()->groupBy('book_id');
    $lines = ['# Reading Highlights', ''];

    foreach ($grouped as $items) {
        $b = $items->first()?->book;
        if (! $b) {
            continue;
        }
        $heading = '## '.$b->title.($b->author ? ' — '.$b->author : '');
        $lines[] = $heading;
        $lines[] = '';

        foreach ($items as $h) {
            $lines[] = '> '.$h->quote;
            $meta = [];
            if ($h->page_number) {
                $meta[] = 'p. '.$h->page_number;
            }
            foreach ($h->tagList() as $tag) {
                $meta[] = '#'.$tag;
            }
            if ($meta !== []) {
                $lines[] = '> — *'.implode(' · ', $meta).'*';
            }
            if ($h->note) {
                $lines[] = '';
                $lines[] = '**Note:** '.$h->note;
            }
            $lines[] = '';
        }
    }

    return response(implode("\n", $lines), 200, [
        'Content-Type' => 'text/markdown; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="'.$filename.'"',
    ]);
})->name('read.highlights.export');

Route::post('/read/sessions/stop-active', function (Request $request) {
    $bookId = (int) $request->input('book_id');
    if ($bookId <= 0) {
        return response()->noContent();
    }

    $running = ReadingSession::query()
        ->where('book_id', $bookId)
        ->whereNull('finished_at')
        ->orderByDesc('started_at')
        ->first();

    if ($running) {
        $finished = Clock::now()->seconds(0)->utc();
        if ($finished->lte($running->started_at)) {
            $finished = $running->started_at->clone()->addMinute();
        }
        $running->update(['finished_at' => $finished]);
        Onboarding::complete('read.stop');
    }

    return response()->noContent();
})->name('read.sessions.stop-active');

// Secret, unlinked job-applications dashboard: unguessable path (JOBS_PAGE_TOKEN) + the normal login.
if (filled(config('jobs.page_token'))) {
    Route::get('/x/{token}', JobApplications::class)
        ->where('token', preg_quote((string) config('jobs.page_token'), '/'))
        ->name('jobs.secret');
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
