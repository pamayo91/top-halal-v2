<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table): void {
            $table->string('duplicate_signal', 32)->nullable()->after('status')->index();
            $table->json('duplicate_details')->nullable()->after('duplicate_signal');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table): void {
            $table->dropIndex(['duplicate_signal']);
            $table->dropColumn(['duplicate_signal', 'duplicate_details']);
        });
    }
};
