<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('restaurant_web_enrichments', function (Blueprint $table): void { $table->json('matching')->nullable()->after('sources'); $table->string('activity_status', 40)->nullable()->after('matching'); $table->json('facts')->nullable()->after('activity_status'); }); }
    public function down(): void { Schema::table('restaurant_web_enrichments', fn (Blueprint $table) => $table->dropColumn(['matching','activity_status','facts'])); }
};
