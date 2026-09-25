<?php

use App\Livewire\BacklogDripMatrix;
use App\Livewire\DripMatrix;
use App\Livewire\LinkHub;
use App\Livewire\TaskLogBoard;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/milestones');

Route::view('/milestones', 'milestones')->name('milestones');

Route::get('/milestones/drip-matrix', DripMatrix::class)->name('milestones.drip-matrix');

Route::get('/milestones/log', TaskLogBoard::class)->name('milestones.log');
Route::view('/backlog', 'backlog')->name('backlog');

Route::get('/backlog/drip-matrix', BacklogDripMatrix::class)->name('backlog.drip-matrix');

Route::view('/roadmaps', 'roadmaps')->name('roadmaps');

Route::redirect('/links', '/links/partners');

Route::get('/links/{hub}/{slug?}', LinkHub::class)
    ->whereIn('hub', ['partners', 'assets'])
    ->name('links.hub');
