<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Restaurant management and ownership are relationship-based. The former
     * value marked non-owner depositors as technical owners, so it cannot be
     * retained as an authority signal.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'restaurant_owner')->update(['role' => 'user']);
    }

    /** The prior value cannot be reconstructed safely from a role alone. */
    public function down(): void
    {
        // Intentionally no-op: approved claims and submissions remain intact.
    }
};
