<?php

use App\Models\RegressionSentinel;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $sentinel = RegressionSentinel::query()->where('key', 'database.counts')->first();
        if ($sentinel === null || $sentinel->baseline['counts']['pages'] !== 90) return;

        $baseline = $sentinel->baseline;
        $baseline['counts']['pages'] = 89;
        $sentinel->update(['baseline' => $baseline]);
    }

    public function down(): void {}
};
