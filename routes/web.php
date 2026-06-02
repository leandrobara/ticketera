<?php

use App\Http\Controllers\Dashboard\ShowController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard/shows', [ShowController::class, 'index']);
