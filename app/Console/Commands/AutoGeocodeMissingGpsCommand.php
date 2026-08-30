<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\Location\MissingGpsAutoGeocoder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AutoGeocodeMissingGpsCommand extends Command
{
    protected $signature = 'data:autogeocode-missing-gps {--dry-run} {--apply} {--out=docs/generated/missing-gps-autogeocoding-report.md} {--chunk=100}';
    protected $description = 'Automatically geocodes structured addresses missing one or both GPS coordinates without replacing existing coordinates.';

    public function handle(MissingGpsAutoGeocoder $geocoder): int
    {
        if (!$this->option('dry-run') && !$this->option('apply')) { $this->error('Choose --dry-run or --apply.'); return self::FAILURE; }
        $apply = (bool) $this->option('apply');
        $base = Restaurant::query()->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'));
        $before = (clone $base)->count(); $stats = ['before'=>$before, 'queried'=>0, 'added'=>0, 'housenumber'=>0, 'street'=>0, 'other'=>0, 'refused'=>0, 'provider_error'=>0, 'no_result'=>0, 'unusable_address'=>0, 'unexpected_country'=>0, 'existing_gps_modified'=>0];
        $base->orderBy('id')->chunkById(max(1, (int) $this->option('chunk')), function ($restaurants) use ($geocoder, $apply, &$stats): void {
            foreach ($restaurants as $restaurant) {
                $result = $geocoder->locate($restaurant); $outcome = $result['outcome'];
                if (in_array($outcome, ['housenumber', 'street'], true)) {
                    $stats['queried']++; $stats[$outcome]++;
                    if ($apply) { $before = [$restaurant->latitude, $restaurant->longitude]; $restaurant->forceFill($result['fields'])->save(); if (($before[0] !== null && (string) $before[0] !== (string) $restaurant->latitude) || ($before[1] !== null && (string) $before[1] !== (string) $restaurant->longitude)) $stats['existing_gps_modified']++; }
                    $stats['added']++; continue;
                }
                if (!in_array($outcome, ['unusable_address', 'unexpected_country'], true)) $stats['queried']++;
                if (array_key_exists($outcome, $stats)) $stats[$outcome]++; else $stats['refused']++;
            }
        });
        $stats['after'] = Restaurant::query()->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))->count();
        $lines = ['# Auto-géocodage des restaurants sans GPS', '', '- Mode : `'.($apply ? 'apply' : 'dry-run').'`', '- Restaurants sans GPS avant : **'.$stats['before'].'**', '- Interrogés : **'.$stats['queried'].'**', '- GPS ajoutés automatiquement : **'.$stats['added'].'**', '- housenumber : '.$stats['housenumber'], '- street : '.$stats['street'], '- autres : '.$stats['other'], '- Refus pour contradiction : '.$stats['refused'], '- Aucun résultat : '.$stats['no_result'], '- Échecs provider : '.$stats['provider_error'], '- Adresse inexploitable : '.$stats['unusable_address'], '- Pays inattendu : '.$stats['unexpected_country'], '- Toujours sans GPS après : **'.$stats['after'].'**', '- GPS existants modifiés : **'.$stats['existing_gps_modified'].'**'];
        File::ensureDirectoryExists(dirname(base_path($this->option('out')))); File::put(base_path($this->option('out')), implode("\n", $lines)."\n");
        $this->info('Auto-geocoding complete: '.$stats['added'].' added, '.$stats['after'].' remaining.');
        return self::SUCCESS;
    }
}
