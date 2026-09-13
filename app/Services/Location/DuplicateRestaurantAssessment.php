<?php

namespace App\Services\Location;

use Illuminate\Support\Collection;

/** @phpstan-type DuplicateMatch array{restaurant: \App\Models\Restaurant, reason: string} */
class DuplicateRestaurantAssessment
{
    /** @param Collection<int, array{restaurant: \App\Models\Restaurant, reason: string}> $certain
     *  @param Collection<int, array{restaurant: \App\Models\Restaurant, reason: string}> $potential */
    public function __construct(public readonly Collection $certain, public readonly Collection $potential)
    {
    }

    /** @return Collection<int, array{restaurant: \App\Models\Restaurant, reason: string}> */
    public function candidates(): Collection
    {
        return $this->certain->concat($this->potential)->unique(fn (array $match) => $match['restaurant']->id)->values();
    }
}
