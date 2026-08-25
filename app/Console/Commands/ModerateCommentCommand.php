<?php

namespace App\Console\Commands;

use App\Models\Comment;
use Illuminate\Console\Command;

class ModerateCommentCommand extends Command
{
    protected $signature = 'comments:moderate {id : V2 comment ID} {--status= : approved, rejected or spam} {--delete : Permanently remove the comment}';

    protected $description = 'Provides the temporary technical moderation backend until the admin module exists.';

    public function handle(): int
    {
        $comment = Comment::findOrFail((int) $this->argument('id'));
        if ($this->option('delete')) {
            $comment->delete();
            $this->info('Comment deleted.');
            return self::SUCCESS;
        }
        $status = $this->option('status');
        if (!in_array($status, ['approved', 'rejected', 'spam'], true)) {
            $this->error('Use --status=approved|rejected|spam or --delete.');
            return self::FAILURE;
        }
        $comment->update(['status' => $status, 'approved_at' => $status === 'approved' ? now() : null]);
        $this->info('Comment moderated.');
        return self::SUCCESS;
    }
}
