<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\{Article, Comment, Page};
use Illuminate\Http\RedirectResponse;

class PreviewCommentController extends Controller
{
    public function store(StoreCommentRequest $request, string $type, int $legacyId): RedirectResponse
    {
        $content = $this->content($type, $legacyId);
        Comment::create([
            $type === 'post' ? 'article_id' : 'page_id' => $content->id,
            'author_name' => $request->validated('name'),
            'author_email' => $request->validated('email'),
            'content' => trim(strip_tags($request->validated('content'))),
            'status' => 'pending',
        ]);
        return back()->with('comment_submitted', true);
    }

    private function content(string $type, int $legacyId): Article|Page
    {
        $model = $type === 'post' ? Article::class : ($type === 'page' ? Page::class : abort(404));
        return $model::where('legacy_wp_id', $legacyId)->firstOrFail();
    }
}
