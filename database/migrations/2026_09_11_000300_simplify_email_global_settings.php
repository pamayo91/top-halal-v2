<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $setting = DB::table('settings')->where('key', 'email_global_settings')->first();

        if (! $setting) {
            return;
        }

        $values = json_decode((string) $setting->value, true) ?: [];

        if (($values['footer_text'] ?? null) === 'Top Halal') {
            $values['footer_text'] = "Une question ? Notre équipe est à votre écoute.\nÀ très bientôt sur Top Halal !\nL'équipe Top Halal";
        }

        unset($values['primary_color'], $values['show_current_year'], $values['footer_additional_text']);
        DB::table('settings')->where('id', $setting->id)->update(['value' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_at' => now()]);
    }

    public function down(): void {}
};
