<?php

namespace App\Console\Commands;

use App\Models\{Article, Category, Comment, Feature, Location, Page, Restaurant, RestaurantReview, User};
use App\Services\TextNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditTextEncodingCommand extends Command
{
    protected $signature = 'data:audit-text-encoding {--apply : Apply only deterministic normalisations} {--out=docs/generated/text-encoding-audit.md}';
    protected $description = 'Audits V2 plain text fields and records deterministic encoding corrections.';

    public function handle(TextNormalizer $normalizer): int
    {
        $targets = [
            Restaurant::class => ['name', 'description', 'address', 'city_name', 'phone', 'contact_email', 'seo_title', 'seo_description'],
            Article::class => ['original_title', 'title', 'excerpt', 'seo_title', 'seo_description'],
            Page::class => ['original_title', 'title', 'excerpt', 'seo_title', 'seo_description'],
            Comment::class => ['author_name', 'author_email', 'content'], RestaurantReview::class => ['author_name', 'author_email', 'title', 'content'],
            User::class => ['name', 'email'], Category::class => ['name', 'slug'], Feature::class => ['name', 'slug'], Location::class => ['name', 'slug'],
        ];
        $detected = $corrected = $ambiguous = 0; $examples = [];
        foreach ($targets as $model => $columns) {
            $model::query()->orderBy('id')->each(function ($record) use ($columns, $normalizer, &$detected, &$corrected, &$ambiguous, &$examples): void {
                $dirty = [];
                foreach ($columns as $column) {
                    if ($record->{$column} === null) continue;
                    $result = $normalizer->normalizePlainText($record->{$column});
                    if (! $result['changed'] && ! $result['ambiguous']) continue;
                    $detected++;
                    if ($result['ambiguous']) { $ambiguous++; continue; }
                    $dirty[$column] = $result['value']; $corrected++;
                    if (count($examples) < 20) $examples[] = [$record::class, $record->id, $column, $record->{$column}, $result['value']];
                }
                if ($dirty !== [] && $this->option('apply')) $record->forceFill($dirty)->save();
            });
        }
        $lines = ['# Audit encodage V2', '', 'Mode : `'.($this->option('apply') ? 'correction appliquée' : 'lecture seule').'`', '', "- Valeurs détectées : {$detected}", "- Valeurs corrigibles : {$corrected}", "- Cas ambigus non modifiés : {$ambiguous}", '', '## Exemples', ''];
        foreach ($examples as [$class, $id, $column, $before, $after]) $lines[] = sprintf('- `%s#%d.%s` : `%s` → `%s`', class_basename($class), $id, $column, str_replace('`', '\\`', $before), str_replace('`', '\\`', $after));
        File::put(base_path($this->option('out')), implode("\n", $lines)."\n");
        $this->info("{$detected} value(s) detected; {$corrected} deterministic correction(s).");
        return self::SUCCESS;
    }
}
