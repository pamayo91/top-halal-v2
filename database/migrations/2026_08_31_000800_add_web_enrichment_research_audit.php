<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restaurant_web_enrichments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('research_count')->default(0)->after('attempts');
            $table->json('search_queries')->nullable()->after('sources');
            $table->json('rejected_sources')->nullable()->after('search_queries');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_web_enrichments', fn (Blueprint $table) => $table->dropColumn(['research_count', 'search_queries', 'rejected_sources']));
    }
};
