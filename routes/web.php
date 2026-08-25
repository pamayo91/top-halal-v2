<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'top-halal-v2',
    ]);
})->name('health');
