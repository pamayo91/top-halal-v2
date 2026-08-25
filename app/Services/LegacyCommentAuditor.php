<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LegacyCommentAuditor
{
    public function audit(): array
    {
        $db = DB::connection('legacy_wp');
        $prefix = $db->getTablePrefix();
        $all = $db->table('comments')->select('comment_ID', 'comment_parent')->get();
        $parents = $all->pluck('comment_parent', 'comment_ID')->map(fn ($value) => (int) $value)->all();
        $maxDepth = 0;
        foreach ($parents as $id => $parent) {
            $depth = 0; $seen = [];
            while ($parent && isset($parents[$parent]) && !isset($seen[$parent])) { $seen[$parent] = true; $depth++; $parent = $parents[$parent]; }
            $maxDepth = max($maxDepth, $depth);
        }
        $normal = $db->table('comments')->whereIn('comment_type', ['', 'comment']);
        return [
            'total' => $all->count(),
            'status_and_type' => $db->table('comments')->selectRaw('comment_approved, comment_type, count(*) as count')->groupBy('comment_approved', 'comment_type')->orderBy('comment_approved')->get()->map(fn ($row) => (array) $row)->all(),
            'post_types' => $db->table('comments')
                ->leftJoin('posts', 'posts.ID', '=', 'comments.comment_post_ID')
                ->selectRaw("coalesce(`{$prefix}posts`.`post_type`, 'missing') as post_type, count(*) as count")
                ->groupBy('post_type')->orderByDesc('count')->get()->map(fn ($row) => (array) $row)->all(),
            'parented' => $db->table('comments')->where('comment_parent', '>', 0)->count(),
            'max_thread_depth' => $maxDepth,
            'missing_posts' => $db->table('comments as c')->leftJoin('posts as p', 'p.ID', '=', 'c.comment_post_ID')->whereNull('p.ID')->count(),
            'legacy_users' => $db->table('comments')->where('user_id', '>', 0)->count(),
            'guest_comments' => $db->table('comments')->where('user_id', 0)->count(),
            'commentmeta' => $db->table('commentmeta')->selectRaw('meta_key, count(*) as count')->groupBy('meta_key')->orderByDesc('count')->limit(20)->get()->map(fn ($row) => (array) $row)->all(),
            'signals' => [
                'url_like_content' => (clone $normal)->whereRaw("comment_content regexp 'https?://|www\\\\.'")->count(),
                'html_content' => (clone $normal)->whereRaw("comment_content regexp '<[^>]+>'")->count(),
                'author_urls' => (clone $normal)->where('comment_author_url', '!=', '')->count(),
                'email_present' => (clone $normal)->where('comment_author_email', '!=', '')->count(),
                'phone_like_content' => (clone $normal)->whereRaw("comment_content regexp '[0-9][0-9 .-]{7,}[0-9]'")->count(),
            ],
        ];
    }
}
