<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\Location\AddressLineParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RepairAddressLine1Command extends Command
{
    protected $signature = 'data:repair-address-line1
        {--apply : Persist only the strict, verified address_line1 corrections}
        {--visible-suffix : Also remove an explicit final historical CP/city despite a structured mismatch}
        {--expect=812 : Refuse a write unless this exact number of candidates is found}
        {--out=docs/generated/address-line1-repair-report.json : Persistent JSON report path}';

    protected $description = 'Strictly split the historical postcode/city suffix from address_line1.';

    private const PROTECTED_FIELDS = [
        'address', 'address_line2', 'postal_code', 'city_name', 'city_code', 'country_code',
        'latitude', 'longitude',
    ];

    public function handle(AddressLineParser $lines): int
    {
        $stats = ['examined' => 0, 'corrected' => 0, 'ignored' => 0, 'ambiguous' => 0];
        $candidates = [];
        $violations = [];

        Restaurant::query()->orderBy('id')->chunkById(200, function ($restaurants) use ($lines, &$stats, &$candidates, &$violations): void {
            foreach ($restaurants as $restaurant) {
                $stats['examined']++;
                $inspection = $lines->inspect($restaurant->address_line1, $restaurant->address, $restaurant->postal_code, $restaurant->city_name, (bool) $this->option('visible-suffix'));

                if ($inspection['state'] !== 'candidate') {
                    $stats[$inspection['state']]++;
                    continue;
                }

                $candidates[] = $this->candidate($restaurant, $inspection['new_line1']);
            }
        });

        $expected = (int) $this->option('expect');
        if ($this->option('apply') && count($candidates) !== $expected) {
            $this->writeReport($stats, $candidates, false, "Write refused: expected {$expected} strict candidates, found ".count($candidates).'.');
            $this->error("Write refused: expected {$expected} strict candidates, found ".count($candidates).'.');

            return self::FAILURE;
        }

        if ($this->option('apply')) {
            $candidateIds = collect($candidates)->pluck('id')->all();
            Restaurant::query()->whereIn('id', $candidateIds)->orderBy('id')->chunkById(200, function ($restaurants) use ($lines, &$stats, &$violations): void {
                foreach ($restaurants as $restaurant) {
                    $inspection = $lines->inspect($restaurant->address_line1, $restaurant->address, $restaurant->postal_code, $restaurant->city_name, (bool) $this->option('visible-suffix'));
                    if ($inspection['state'] !== 'candidate') {
                        $violations[] = "Restaurant {$restaurant->id} no longer matches the strict rule.";
                        continue;
                    }

                    $before = $restaurant->only(self::PROTECTED_FIELDS);
                    $restaurant->forceFill(['address_line1' => $inspection['new_line1']])->save();
                    $after = $restaurant->fresh()->only(self::PROTECTED_FIELDS);
                    if ($before !== $after) {
                        $violations[] = "Restaurant {$restaurant->id} changed a protected field.";
                        continue;
                    }

                    $stats['corrected']++;
                }
            });
        }

        $this->writeReport($stats, $candidates, (bool) $this->option('apply'), $violations === [] ? null : implode(' ', $violations));
        $this->line(json_encode([...$stats, 'candidates' => count($candidates), 'mode' => $this->option('apply') ? 'apply' : 'dry-run']));

        return $violations === [] ? self::SUCCESS : self::FAILURE;
    }

    private function candidate(Restaurant $restaurant, string $newLine1): array
    {
        return [
            'id' => $restaurant->id,
            'legacy_wp_id' => $restaurant->legacy_wp_id,
            'slug' => $restaurant->slug,
            'old_address_line1' => $restaurant->address_line1,
            'new_address_line1' => $newLine1,
            'postal_code' => $restaurant->postal_code,
            'city_name' => $restaurant->city_name,
        ];
    }

    private function writeReport(array $stats, array $candidates, bool $applied, ?string $error): void
    {
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'mode' => $applied ? 'apply' : 'dry-run',
            'visible_suffix' => (bool) $this->option('visible-suffix'),
            'expected_candidates' => (int) $this->option('expect'),
            'strict_candidates' => count($candidates),
            'stats' => $stats,
            'error' => $error,
            'candidates' => $candidates,
        ];
        $path = base_path($this->option('out'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }
}
