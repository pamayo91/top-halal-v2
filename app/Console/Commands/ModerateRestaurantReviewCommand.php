<?php

namespace App\Console\Commands;

use App\Models\RestaurantReview;
use Illuminate\Console\Command;

class ModerateRestaurantReviewCommand extends Command
{
    protected $signature = 'reviews:moderate {id} {--status=} {--delete}';
    protected $description = 'Temporary technical moderation for restaurant reviews.';
    public function handle(): int
    {
        $review = RestaurantReview::findOrFail($this->argument('id'));
        if ($this->option('delete')) { $review->delete(); $this->info('Review deleted.'); return self::SUCCESS; }
        $status = $this->option('status'); if (! in_array($status, ['approved', 'rejected', 'spam'], true)) { $this->error('Use --status=approved|rejected|spam or --delete.'); return self::FAILURE; }
        $review->update(['status' => $status, 'approved_at' => $status === 'approved' ? now() : null]); $this->info('Review moderated.'); return self::SUCCESS;
    }
}
