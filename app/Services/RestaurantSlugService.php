<?php

namespace App\Services;

use App\Models\Restaurant;
use Illuminate\Support\Str;

class RestaurantSlugService
{
    public function base(string $name): string
    {
        return Str::slug($name) ?: 'restaurant';
    }

    public function generate(string $name, ?string $cityName = null, ?string $postalCode = null, ?Restaurant $ignore = null): string
    {
        $base = $this->base($name);
        $city = Str::slug((string) $cityName);
        $postalCode = Str::slug((string) $postalCode);

        $candidates = collect([
            $base,
            $city !== '' ? $base.'-'.$city : null,
            $city !== '' && $postalCode !== '' ? $base.'-'.$city.'-'.$postalCode : null,
        ])->filter()->unique()->values();

        foreach ($candidates as $candidate) {
            if (! $this->taken($candidate, $ignore)) return $candidate;
        }

        $prefix = (string) $candidates->last();
        for ($suffix = 2; ; $suffix++) {
            $candidate = $prefix.'-'.$suffix;
            if (! $this->taken($candidate, $ignore)) return $candidate;
        }
    }

    public function taken(string $slug, ?Restaurant $ignore = null): bool
    {
        return Restaurant::withTrashed()
            ->where('slug', $slug)
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->getKey()))
            ->exists();
    }
}
