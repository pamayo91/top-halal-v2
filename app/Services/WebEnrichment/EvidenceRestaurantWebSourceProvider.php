<?php

namespace App\Services\WebEnrichment;

use App\Models\Restaurant;

class EvidenceRestaurantWebSourceProvider implements RestaurantWebSourceProvider
{
    public function __construct(private array $evidence) {}
    public function find(Restaurant $restaurant): array { return $this->evidence[$restaurant->id] ?? ['state'=>'unmatched','sources'=>[],'reason'=>'No evidence submitted for this reserved restaurant']; }
}
