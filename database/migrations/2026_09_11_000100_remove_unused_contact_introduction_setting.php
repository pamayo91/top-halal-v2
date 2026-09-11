<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $setting = DB::table('settings')->where('key', 'contact_settings')->first(['id', 'value']);

        if ($setting === null) {
            return;
        }

        $value = json_decode((string) $setting->value, true);

        if (! is_array($value) || ! array_key_exists('introduction', $value)) {
            return;
        }

        unset($value['introduction']);

        DB::table('settings')->where('id', $setting->id)->update([
            'value' => json_encode($value, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The removed value was unused and is intentionally not recreated.
    }
};
