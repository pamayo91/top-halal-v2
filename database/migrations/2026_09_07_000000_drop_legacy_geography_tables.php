<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('restaurant_location');
        Schema::dropIfExists('locations');
    }

    public function down(): void
    {
        // Forward-only: legacy Geography data is intentionally not recreated.
    }
};
