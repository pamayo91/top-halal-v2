<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Older default rows were created from single-quoted PHP strings and
        // therefore contain the literal two-character sequence "\\n".
        DB::table('email_templates')->orderBy('id')->each(function (object $template): void {
            $body = str_replace(["\\r\\n", "\\n", "\r\n", "\r"], "\n", (string) $template->body);
            if ($body !== $template->body) {
                DB::table('email_templates')->where('id', $template->id)->update(['body' => $body]);
            }
        });
    }

    public function down(): void {}
};
