<?php

namespace App\Services;

use App\Models\{Comment, EmailDeliveryLog, RestaurantReview, Setting};
use Illuminate\Support\Facades\DB;

class ContributionModerationMailer
{
    /**
     * Queue one operational alert for a newly pending public contribution.
     *
     * The delivery-log reference is the durable idempotency key: a repeated
     * confirmation or service call cannot create another business alert.
     */
    public function notifyForPending(RestaurantReview|Comment $contribution): void
    {
        DB::transaction(function () use ($contribution): void {
            $contribution = $contribution instanceof RestaurantReview
                ? RestaurantReview::query()->lockForUpdate()->findOrFail($contribution->id)
                : Comment::query()->lockForUpdate()->findOrFail($contribution->id);

            if ($contribution->status !== 'pending' || $contribution->moderation_notification_log_id !== null) {
                return;
            }

            $recipient = Setting::query()->where('key', 'contact_settings')->first()?->value['recipient'] ?? null;
            if (blank($recipient)) {
                return;
            }

            [$template, $values] = $contribution instanceof RestaurantReview
                ? $this->reviewValues($contribution)
                : $this->commentValues($contribution);

            $log = app(TransactionalMailService::class)->queue(
                $template,
                $recipient,
                $values,
                $contribution->author_email,
            );

            $contribution->update(['moderation_notification_log_id' => $log?->id]);
        });
    }

    /** @return array{string, array<string, string|int>} */
    private function reviewValues(RestaurantReview $review): array
    {
        $review->loadMissing('restaurant');

        return ['restaurant_review_admin_review', [
            'site_name' => config('app.name', 'Top Halal'),
            'restaurant_name' => $review->restaurant->name,
            'author_name' => $review->author_name,
            'rating' => $review->rating,
            'review_excerpt' => (string) str($review->content)->squish()->limit(280),
            'admin_url' => url('/admin/restaurant-reviews?tableFilters[status][value]=pending'),
        ]];
    }

    /** @return array{string, array<string, string>} */
    private function commentValues(Comment $comment): array
    {
        $comment->loadMissing('article', 'page');
        $content = $comment->article ?? $comment->page;

        return ['editorial_comment_admin_review', [
            'site_name' => config('app.name', 'Top Halal'),
            'content_title' => $content?->title ?? 'Contenu indisponible',
            'author_name' => $comment->author_name,
            'comment_excerpt' => (string) str($comment->content)->squish()->limit(280),
            'admin_url' => url('/admin/comments?tableFilters[status][value]=pending'),
        ]];
    }
}
