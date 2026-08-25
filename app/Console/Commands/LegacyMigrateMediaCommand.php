<?php
namespace App\Console\Commands;
use App\Models\MediaAsset;
use App\Services\LegacyMediaReader;
use App\Services\MediaVariantGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class LegacyMigrateMediaCommand extends Command
{
    protected $signature = 'legacy:migrate-media {--ids=} {--dry-run} {--apply} {--out=docs/generated/media-migration-sample}';

    public function handle(LegacyMediaReader $reader, MediaVariantGenerator $variants): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $this->option('ids'))))));
        if ($ids === [] || count($ids) > 10 || ($this->option('dry-run') && $this->option('apply'))) {
            $this->error('Use 1-10 explicit IDs and one mode.');

            return self::FAILURE;
        }

        $report = ['mode' => $this->option('apply') ? 'apply' : 'dry-run', 'requested_ids' => $ids, 'items' => []];
        $attachments = DB::connection('legacy_wp')->table('posts')->whereIn('ID', $ids)->where('post_type', 'attachment')->orderBy('ID')->get();
        $found = $attachments->pluck('ID')->map(fn ($id) => (int) $id)->all();

        foreach (array_values(array_diff($ids, $found)) as $id) {
            $report['items'][] = ['legacy_attachment_id' => $id, 'source_present' => false, 'anomaly' => 'attachment_not_found'];
        }

        foreach ($attachments as $attachment) {
            try {
                $info = $reader->inspect($attachment->guid);
                $item = ['legacy_attachment_id' => $attachment->ID, 'source_present' => true, 'mime' => $info['mime'], 'width' => $info['width'], 'height' => $info['height'], 'bytes' => $info['bytes'], 'checksum' => $info['checksum']];
                if ($this->option('apply')) {
                    $extension = strtolower(pathinfo($info['source'], PATHINFO_EXTENSION)) ?: 'bin';
                    $path = "media/originals/{$info['checksum']}.{$extension}";
                    $disk = Storage::disk(config('legacy-media.disk'));
                    if (! $disk->exists($path)) {
                        $disk->put($path, file_get_contents($info['source']));
                    }
                    $asset = MediaAsset::updateOrCreate(['legacy_attachment_id' => $attachment->ID], [
                        'original_path' => $path, 'mime' => $info['mime'], 'width' => $info['width'], 'height' => $info['height'],
                        'bytes' => $info['bytes'], 'checksum' => $info['checksum'], 'alt_text' => $attachment->post_excerpt ?: null, 'status' => 'ready',
                    ]);
                    $item['destination'] = $asset->original_path;
                    try {
                        $item['variants'] = $variants->generate($asset);
                    } catch (\Throwable) {
                        $asset->update(['status' => 'variant_generation_failed']);
                        $item['variants'] = [];
                        $item['anomaly'] = 'variant_generation_failed';
                    }
                }
                $report['items'][] = $item;
            } catch (\Throwable) {
                $report['items'][] = ['legacy_attachment_id' => $attachment->ID, 'source_present' => false, 'anomaly' => 'missing_or_invalid_source'];
            }
        }

        $base = base_path($this->option('out'));
        File::ensureDirectoryExists(dirname($base));
        File::put($base.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        File::put($base.'.md', "# Media migration pilot\n\n```json\n".json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n```\n");

        return self::SUCCESS;
    }
}
