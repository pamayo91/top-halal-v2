<?php

use Illuminate\Support\Facades\Route;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Page;
use App\Http\Controllers\PreviewCommentController;

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
    $content = $model::where('legacy_wp_id', $legacyId)->firstOrFail();
    $comments = Comment::query()
        ->where($type === 'post' ? 'article_id' : 'page_id', $content->id)
        ->where('status', 'approved')
        ->orderBy('created_at')
        ->get();
    return view('content-preview', compact('content', 'comments', 'type', 'legacyId'));
})->whereIn('type', ['post', 'page']);

Route::post('/_preview/{type}/{legacyId}/comments', [PreviewCommentController::class, 'store'])
    ->middleware('throttle:10,1')
    ->whereIn('type', ['post', 'page']);
