<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** Loads a page of complete, approved editorial discussion threads. */
class CommentThreads
{
    public const PER_PAGE = 20;

    /** @return LengthAwarePaginator<int, Comment> */
    public function for(Article|Page $content): LengthAwarePaginator
    {
        $foreignKey = $content instanceof Article ? 'article_id' : 'page_id';

        $threads = Comment::query()
            ->where($foreignKey, $content->id)
            ->where('status', 'approved')
            ->whereNull('parent_id')
            ->latest('created_at')
            ->paginate(self::PER_PAGE, ['*'], 'comments_page');

        $this->attachApprovedDescendants($threads->getCollection());

        return $threads;
    }

    public function visibleCount(Article|Page $content): int
    {
        $foreignKey = $content instanceof Article ? 'article_id' : 'page_id';

        return Comment::query()->where($foreignKey, $content->id)->where('status', 'approved')->count();
    }

    /**
     * A bounded query is issued per actual nesting level, not per comment.
     * This keeps historical deeply threaded discussions intact without an N+1.
     *
     * @param Collection<int, Comment> $roots
     */
    private function attachApprovedDescendants(Collection $roots): void
    {
        $parents = $roots;

        while ($parents->isNotEmpty()) {
            $children = Comment::query()
                ->where('status', 'approved')
                ->whereIn('parent_id', $parents->pluck('id'))
                ->oldest('created_at')
                ->get()
                ->groupBy('parent_id');

            foreach ($parents as $parent) {
                $parent->setRelation('children', $children->get($parent->id, collect()));
            }

            $parents = $children->flatten(1)->values();
        }
    }
}
