<?php

namespace App\Services\Geocoding;

interface GeocodingService
{
    /** @return array{ok:bool,query:string,features:array,error:?string,cached:bool} */
    public function search(string $query, int $limit = 3): array;
    /** @return array{ok:bool,query:string,features:array,error:?string,cached:bool} */
    public function reverse(float $latitude, float $longitude, int $limit = 3): array;
}
