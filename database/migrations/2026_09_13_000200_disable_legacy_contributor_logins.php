<?php

use IlluminateDatabaseMigrationsMigration;
use IlluminateDatabaseQueryBuilder;
use IlluminateSupportFacadesDB;

return new class extends Migration
{
    /**
     * Legacy contributor identities were given the default login_enabled=true
     * when the column was introduced. Disable only the deterministic set that
     * has never activated an account and has no restaurant authority.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('legacy_wp_user_id')
            ->where('role', 'user')
            ->where('login_enabled', true)
            ->whereNull('email_verified_at')
            ->where('must_change_password', true)
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('restaurant_claims')
                ->whereColumn('restaurant_claims.user_id', 'users.id'))
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('restaurant_submissions')
                ->whereColumn('restaurant_submissions.user_id', 'users.id'))
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('legacy_restaurant_authorships')
                ->whereColumn('legacy_restaurant_authorships.user_id', 'users.id'))
            ->where(function (Builder $query) {
                $query->whereExists(fn (Builder $reviews) => $reviews->selectRaw('1')
                    ->from('restaurant_reviews')
                    ->where(fn (Builder $match) => $match
                        ->whereColumn('restaurant_reviews.legacy_user_id', 'users.legacy_wp_user_id')
                        ->orWhereColumn('restaurant_reviews.author_email', 'users.email')))
                    ->orWhereExists(fn (Builder $comments) => $comments->selectRaw('1')
                        ->from('comments')
                        ->where(fn (Builder $match) => $match
                            ->whereColumn('comments.legacy_user_id', 'users.legacy_wp_user_id')
                            ->orWhereColumn('comments.author_email', 'users.email')));
            })
            ->update(['login_enabled' => false]);
    }

    /** The former login permission cannot be inferred safely after correction. */
    public function down(): void
    {
        // Intentionally no-op.
    }
};
