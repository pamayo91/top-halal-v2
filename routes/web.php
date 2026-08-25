<?php

use Illuminate\Support\Facades\Route;
use App\Models\Article;
use App\Models\Page;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'top-halal-v2',
    ]);
})->name('health');

Route::get('/_preview/{type}/{legacyId}', function (string $type, int $legacyId) {
    $model = $type === 'post' ? Article::class : ($type === 'page' ? Page::class : abort(404));
    return view('content-preview', ['content' => $model::where('legacy_wp_id', $legacyId)->firstOrFail()]);
})->whereIn('type', ['post', 'page']);
