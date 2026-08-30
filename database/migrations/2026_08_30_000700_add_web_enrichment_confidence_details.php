<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('restaurant_web_enrichments', function (Blueprint $table): void { $table->string('previous_status',40)->nullable()->after('status'); $table->string('confidence_level',10)->nullable()->after('confidence'); $table->string('matching_confidence',10)->nullable()->after('confidence_level'); $table->string('activity_confidence',10)->nullable()->after('matching_confidence'); $table->string('hours_confidence',10)->nullable()->after('activity_confidence'); $table->string('description_confidence',10)->nullable()->after('hours_confidence'); }); }
    public function down(): void { Schema::table('restaurant_web_enrichments', fn(Blueprint $t)=>$t->dropColumn(['previous_status','confidence_level','matching_confidence','activity_confidence','hours_confidence','description_confidence'])); }
};
