<?php

use App\Livewire\DripMatrix;
use App\Livewire\LinkHub;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/milestones');

Route::view('/milestones', 'milestones')->name('milestones');

Route::get('/milestones/drip-matrix', DripMatrix::class)->name('milestones.drip-matrix');

Route::view('/backlog', 'backlog')->name('backlog');

Route::view('/roadmaps', 'roadmaps')->name('roadmaps');

Route::redirect('/links', '/links/partners');

Route::get('/links/{hub}/{slug?}', LinkHub::class)
    ->whereIn('hub', ['partners', 'assets'])
    ->name('links.hub');
