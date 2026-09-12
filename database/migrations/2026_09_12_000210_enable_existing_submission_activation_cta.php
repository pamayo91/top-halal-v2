<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')
            ->where('key', 'restaurant_submission_email_confirmed')
            ->whereNull('cta_label')
            ->update(['cta_label' => 'Activer mon espace', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('key', 'restaurant_submission_email_confirmed')
            ->where('cta_label', 'Activer mon espace')
            ->update(['cta_label' => null, 'updated_at' => now()]);
    }
};
