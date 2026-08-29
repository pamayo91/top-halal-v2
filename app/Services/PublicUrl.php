<?php

namespace App\Services;

use App\Models\{Article, Category, Comment, Feature, Location, MediaAsset, Page, Restaurant, RestaurantClaim, RestaurantReview};

class PublicUrl
{
    public function for(object $record): ?string
    {
        return match (true) {
            $record instanceof Restaurant => $record->status === 'published' ? route('restaurants.show', $record->slug) : null,
            $record instanceof Article, $record instanceof Page => $record->status === 'published' ? route('editorial.show', $record->slug) : null,
            $record instanceof RestaurantReview => $this->forReview($record),
            $record instanceof Comment => $this->forComment($record),
            $record instanceof RestaurantClaim => $this->for($record->restaurant),
            $record instanceof MediaAsset => route('media.show', $record),
            $record instanceof Location => $record->restaurants()->exists() ? route('locations.show', $record->slug) : null,
            $record instanceof Category => $record->restaurants()->exists() ? route('categories.show', $record->slug) : null,
            $record instanceof Feature => $record->restaurants()->exists() ? route('features.show', $record->slug) : null,
            default => null,
        };
    }

    private function forReview(RestaurantReview $review): ?string
    {
        $url = $this->for($review->restaurant);
        return $url ? $url.'#avis' : null;
    }

    private function forComment(Comment $comment): ?string
    {
        $content = $comment->article ?? $comment->page;
        $url = $content ? $this->for($content) : null;
        return $url ? $url.'#comment-'.$comment->id : null;
    }
}
