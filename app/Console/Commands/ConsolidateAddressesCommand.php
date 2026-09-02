<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\Geocoding\GeocodingService;
use App\Services\Location\AddressLineParser;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

class ConsolidateAddressesCommand extends Command
{
    protected $signature = 'data:consolidate-addresses
        {--apply : Persist only currently missing structured fields}
        {--dry-run : Do not persist anything}
        {--from-id= : Inclusive restaurant ID lower bound}
        {--to-id= : Inclusive restaurant ID upper bound}
        {--ids= : Comma-separated restaurant IDs to process}
        {--out=docs/generated/address-consolidation-report.md : Markdown report path}';

    public function handle(GeocodingService $geo, AddressLineParser $lines): int
    {
        $ids = collect(explode(',', (string) $this->option('ids')))
            ->map(fn (string $id) => (int) trim($id))
            ->filter()
            ->values()
            ->all();

        $rows = Restaurant::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($ids !== [], fn (Builder $query) => $query->whereIn('id', $ids))
            ->when($ids === [], fn (Builder $query) => $query->where(function (Builder $query): void {
                foreach (['address_line1', 'postal_code', 'city_name', 'city_code', 'country_code'] as $field) {
                    $query->orWhereNull($field)->orWhere($field, '');
                }
            }))
            ->when($this->option('from-id'), fn (Builder $query, $id) => $query->where('id', '>=', $id))
            ->when($this->option('to-id'), fn (Builder $query, $id) => $query->where('id', '<=', $id))
            ->orderBy('id')
            ->get();

        $stats = ['selected' => $rows->count(), 'asked' => 0, 'cached' => 0, 'filled' => 0, 'incomplete_provider_result' => 0, 'provider_errors' => 0];

        foreach ($rows as $restaurant) {
            $result = $geo->reverse((float) $restaurant->latitude, (float) $restaurant->longitude);
            $stats['asked']++;
            $stats['cached'] += (int) $result['cached'];

            if (! $result['ok']) {
                $stats['provider_errors']++;
                $this->warn("Restaurant {$restaurant->id}: {$result['error']}");
                continue;
            }

            $feature = $result['features'][0] ?? null;
            if (! $feature || ! $feature['postcode'] || ! $feature['city'] || ! $feature['citycode']) {
                $stats['incomplete_provider_result']++;
                continue;
            }

            $line1 = $lines->fromHistoricalOrProvider($restaurant->address, $feature['label'] ?? null, $feature['postcode'], $feature['city']);
            if (! $line1) {
                $stats['incomplete_provider_result']++;
                continue;
            }

            if ($this->option('apply')) {
                $restaurant->forceFill([
                    'address_line1' => $restaurant->address_line1 ?: $line1,
                    'postal_code' => $restaurant->postal_code ?: $feature['postcode'],
                    'city_name' => $restaurant->city_name ?: $feature['city'],
                    'city_code' => $restaurant->city_code ?: $feature['citycode'],
                    'country_code' => $restaurant->country_code ?: 'FR',
                ])->save();
            }

            $stats['filled']++;
        }

        File::put(base_path($this->option('out')), "# Consolidation des adresses\n\n"
            ."- Sélectionnés : {$stats['selected']}\n- Résultats Géoplateforme lus : {$stats['asked']}\n- Réponses cache : {$stats['cached']}\n- Fiches complétables : {$stats['filled']}\n- Résultats fournisseur incomplets : {$stats['incomplete_provider_result']}\n- Erreurs fournisseur : {$stats['provider_errors']}\n- GPS modifiés : 0\n- Adresse historique modifiée : 0\n");

        $this->info(json_encode($stats));

        return self::SUCCESS;
    }
}
