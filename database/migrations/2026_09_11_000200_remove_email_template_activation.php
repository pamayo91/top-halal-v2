<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('email_templates', 'is_active')) {
            Schema::table('email_templates', fn ($table) => $table->dropColumn('is_active'));
        }
    }

    public function down(): void
    {
        Schema::table('email_templates', fn ($table) => $table->boolean('is_active')->default(true));
    }
};
