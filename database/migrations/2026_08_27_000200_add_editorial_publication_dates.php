<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { foreach (['articles','pages'] as $table) Schema::table($table, fn (Blueprint $t) => $t->timestamp('published_at')->nullable()->index()->after('status')); } public function down(): void { foreach (['articles','pages'] as $table) Schema::table($table, fn (Blueprint $t) => $t->dropColumn('published_at')); } };
