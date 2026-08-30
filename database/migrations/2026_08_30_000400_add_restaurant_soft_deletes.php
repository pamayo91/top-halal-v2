<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', fn (Blueprint $table) => $table->softDeletes());

        DB::table('restaurants')
            ->where('status', 'archived')
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
