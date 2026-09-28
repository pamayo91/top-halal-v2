<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['articles', 'pages'] as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->softDeletes());
            }
        }

        Schema::table('redirect_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('redirect_rules', 'related_type')) $table->string('related_type', 32)->nullable()->index();
            if (! Schema::hasColumn('redirect_rules', 'related_id')) $table->unsignedBigInteger('related_id')->nullable()->index();
            $table->string('destination', 2048)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('redirect_rules', function (Blueprint $table): void {
            $table->dropIndex('redirect_rules_related_type_index');
            $table->dropIndex('redirect_rules_related_id_index');
            if (Schema::hasColumn('redirect_rules', 'related_type')) $table->dropColumn('related_type');
            if (Schema::hasColumn('redirect_rules', 'related_id')) $table->dropColumn('related_id');
        });
        foreach (['articles', 'pages'] as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropSoftDeletes());
        }
    }
};
