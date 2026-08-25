<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LegacyCommentReader
{
    public function findMany(array $ids = [], array $postIds = []): array
    {
        $query = DB::connection('legacy_wp')->table('comments')
            ->whereIn('comment_approved', ['0', '1'])
            ->whereIn('comment_type', ['', 'comment']);
        if ($ids !== []) $query->whereIn('comment_ID', $ids);
        if ($postIds !== []) $query->whereIn('comment_post_ID', $postIds);

        return $query->orderBy('comment_ID')->get()->all();
    }

    public function find(int $id): ?object
    {
        return DB::connection('legacy_wp')->table('comments')->where('comment_ID', $id)->first();
    }
}
