<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\Geocoding\GeocodingService;
use App\Services\Location\AddressLineParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CompleteMissingStructuredAddressesCommand extends Command
{
    protected $signature = 'data:complete-missing-structured-addresses
        {--apply : Persist only complete provider-derived structured fields}
        {--expect= : Refuse an apply unless this exact candidate total is found}
        {--out=docs/generated/missing-structured-addresses-report.json : Persistent JSON report path}';

    private const PROTECTED_FIELDS = [
        'address', 'address_line2', 'latitude', 'longitude',
    ];

    public function handle(GeocodingService $geo, AddressLineParser $lines): int
    {
        $stats = ['selected' => 0, 'asked' => 0, 'cached' => 0, 'candidates' => 0, 'corrected' => 0, 'no_result' => 0, 'incomplete' => 0, 'insufficient_precision' => 0, 'provider_errors' => 0];
        $candidates = [];
        $violations = [];

        $this->missingQuery()->orderBy('id')->chunkById(100, function ($restaurants) use ($geo, $lines, &$stats, &$candidates): void {
            foreach ($restaurants as $restaurant) {
                $stats['selected']++;
                $result = filled($restaurant->address) ? $geo->search($restaurant->address) : ['ok' => false, 'features' => [], 'cached' => true, 'error' => 'empty_address'];
                $stats['asked'] += (int) filled($restaurant->address);
                $stats['cached'] += (int) ($result['cached'] ?? false);
                if (! ($result['ok'] ?? false)) { $stats['provider_errors']++; continue; }
                $feature = $result['features'][0] ?? null;
                if (! $feature) { $stats['no_result']++; continue; }
                if (! filled($feature['postcode'] ?? null) || ! filled($feature['city'] ?? null) || ! filled($feature['citycode'] ?? null)) { $stats['incomplete']++; continue; }
                if (! in_array($feature['type'] ?? null, ['housenumber', 'street'], true)) { $stats['insufficient_precision']++; continue; }
                $line1 = $lines->fromProviderLabel($feature['label'] ?? null, $feature['postcode'], $feature['city']);
                if (! $line1) { $stats['incomplete']++; continue; }

                $candidates[] = ['id' => $restaurant->id, 'legacy_wp_id' => $restaurant->legacy_wp_id, 'slug' => $restaurant->slug, 'address' => $restaurant->address, 'address_line1' => $line1, 'postal_code' => $feature['postcode'], 'city_name' => $feature['city'], 'city_code' => $feature['citycode'], 'country_code' => 'FR'];
            }
        });
        $stats['candidates'] = count($candidates);

        if ($this->option('apply') && ($this->option('expect') === null || $stats['candidates'] !== (int) $this->option('expect'))) {
            $this->write($stats, $candidates, false, 'Write refused: candidate total does not match --expect.');
            return self::FAILURE;
        }

        if ($this->option('apply')) {
            foreach (collect($candidates)->chunk(100) as $chunk) {
                foreach ($chunk as $candidate) {
                    $restaurant = $this->missingQuery()->find($candidate['id']);
                    if (! $restaurant) { $violations[] = "Restaurant {$candidate['id']} no longer has only missing structured fields."; continue; }
                    $before = $restaurant->only(self::PROTECTED_FIELDS);
                    $restaurant->forceFill([
                        'address_line1' => $candidate['address_line1'], 'postal_code' => $candidate['postal_code'], 'city_name' => $candidate['city_name'],
                        'city_code' => $candidate['city_code'], 'country_code' => $candidate['country_code'],
                    ])->save();
                    if ($before !== $restaurant->fresh()->only(self::PROTECTED_FIELDS)) { $violations[] = "Restaurant {$candidate['id']} changed a protected field."; continue; }
                    $stats['corrected']++;
                }
            }
        }
        $this->write($stats, $candidates, (bool) $this->option('apply'), $violations === [] ? null : implode(' ', $violations));
        $this->line(json_encode([...$stats, 'mode' => $this->option('apply') ? 'apply' : 'dry-run']));

        return $violations === [] ? self::SUCCESS : self::FAILURE;
    }

    private function missingQuery()
    {
        return Restaurant::query()
            ->where(fn ($q) => $q->whereNull('address_line1')->orWhere('address_line1', ''))
            ->where(fn ($q) => $q->whereNull('postal_code')->orWhere('postal_code', ''))
            ->where(fn ($q) => $q->whereNull('city_name')->orWhere('city_name', ''));
    }

    private function write(array $stats, array $candidates, bool $applied, ?string $error): void
    {
        $path = base_path($this->option('out'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(['generated_at' => now()->toIso8601String(), 'mode' => $applied ? 'apply' : 'dry-run', 'expected_candidates' => $this->option('expect'), 'stats' => $stats, 'error' => $error, 'candidates' => $candidates], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }
}
