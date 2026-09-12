<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\{Article, Page};
use App\Services\ContributionIdentityService;
use Illuminate\Http\RedirectResponse;

class PreviewCommentController extends Controller
{
    public function store(StoreCommentRequest $request, string $type, int $legacyId, ContributionIdentityService $identities): RedirectResponse
    {
        $content = $this->content($type, $legacyId);
        $result = $identities->submitComment($request, $content, $request->validated());

        return back()->with($result['verified'] ? 'comment_submitted' : 'contribution_verification_sent', true);
    }

    private function content(string $type, int $legacyId): Article|Page
    {
        $model = $type === 'post' ? Article::class : ($type === 'page' ? Page::class : abort(404));
        return $model::where('legacy_wp_id', $legacyId)->firstOrFail();
    }
}
