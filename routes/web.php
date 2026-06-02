<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ShowController;

Route::get('/', function () {
    return view('welcome');
});

Route::redirect('/admin', '/admin/shows');
Route::get('/admin/login', [ShowController::class, 'index']);
Route::get('/admin/shows', [ShowController::class, 'index']);
