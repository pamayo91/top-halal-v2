<?php

namespace App\Console\Commands;

use App\Services\LegacyMediaReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LegacyAuditMediaCommand extends Command
{
    protected $signature = 'legacy:audit-media {--out=docs/generated/media-audit}';

    public function handle(LegacyMediaReader $reader): int
    {
        $legacy = DB::connection('legacy_wp');
        $attachments = $legacy->table('posts')->where('post_type', 'attachment')->select('ID', 'guid')->get();
        $inlineDebt = 0;

        foreach ($legacy->table('posts')->whereIn('post_type', ['post', 'page'])->where('post_content', 'like', '%top-halal.fr/wp-conten%')->pluck('post_content') as $html) {
            $inlineDebt += preg_match_all('/<img\b[^>]*\bsrc=["\'][^"\']*top-halal\.fr\/wp-conten(?:t|u)[^"\']*["\'][^>]*>/i', $html);
        }

        $report = [
            'attachments' => $attachments->count(), 'physical_files' => 0, 'present' => 0, 'missing' => 0, 'mime' => [],
            'featured_images' => $legacy->table('postmeta')->where('meta_key', '_thumbnail_id')->where('meta_value', '!=', '')->count(),
            'restaurant_galleries' => $legacy->table('postmeta')->where('meta_key', 'gallery_image_ids')->where('meta_value', '!=', '')->count(),
            'inline_debt' => $inlineDebt, 'duplicates' => 0, 'anomalies' => [],
        ];
        $checksums = [];
        foreach ($attachments as $attachment) {
            try {
                $info = $reader->inspect($attachment->guid);
                $report['present']++;
                $report['mime'][$info['mime']] = ($report['mime'][$info['mime']] ?? 0) + 1;
                $checksums[$info['checksum']] = ($checksums[$info['checksum']] ?? 0) + 1;
            } catch (\Throwable) {
                $report['missing']++;
            }
        }

        $root = config('legacy-media.uploads_path');
        $report['physical_files'] = $root && is_dir($root) ? iterator_count(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS))) : 0;
        $report['duplicates'] = count(array_filter($checksums, fn ($count) => $count > 1));
        $base = base_path($this->option('out'));
        File::ensureDirectoryExists(dirname($base));
        File::put($base.'.json', json_encode($report, JSON_PRETTY_PRINT));
        File::put($base.'.md', "# Media audit\n\n```json\n".json_encode($report, JSON_PRETTY_PRINT)."\n```\n");
        $this->info('Media audit written.');

        return self::SUCCESS;
    }
}
