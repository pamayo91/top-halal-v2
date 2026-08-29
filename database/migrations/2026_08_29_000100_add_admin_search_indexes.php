<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restaurants', fn (Blueprint $t) => $t->index(['status', 'name'], 'restaurants_status_name_index'));
        Schema::table('restaurant_reviews', fn (Blueprint $t) => $t->index(['restaurant_id', 'author_name'], 'reviews_restaurant_author_index'));
        Schema::table('comments', fn (Blueprint $t) => $t->index(['author_name', 'author_email'], 'comments_author_email_index'));
        Schema::table('redirect_rules', fn (Blueprint $t) => $t->index('destination', 'redirect_rules_destination_index'));
    }
    public function down(): void
    {
        Schema::table('restaurants', fn (Blueprint $t) => $t->dropIndex('restaurants_status_name_index'));
        Schema::table('restaurant_reviews', fn (Blueprint $t) => $t->dropIndex('reviews_restaurant_author_index'));
        Schema::table('comments', fn (Blueprint $t) => $t->dropIndex('comments_author_email_index'));
        Schema::table('redirect_rules', fn (Blueprint $t) => $t->dropIndex('redirect_rules_destination_index'));
    }
};
