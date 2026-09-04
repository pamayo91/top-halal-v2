<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Native V2 taxonomy records use a reserved synthetic legacy identifier
     * because the initial catalogue schema retains this non-null legacy field.
     */
    public function up(): void
    {
        $now = now();

        foreach ([
            ['legacy_term_id' => 9000000000000000001, 'name' => 'Burger', 'slug' => 'burger'],
            ['legacy_term_id' => 9000000000000000002, 'name' => 'Brunch', 'slug' => 'brunch'],
            ['legacy_term_id' => 9000000000000000003, 'name' => 'Grillades', 'slug' => 'grillades'],
        ] as $specialty) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $specialty['slug']],
                [...$specialty, 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    public function down(): void
    {
        // Taxonomy entries can be attached to restaurants after this release.
        // They must therefore not be removed automatically on rollback.
    }
};
