<?php

return [
    // Admin-only map presentation. Business/location services never depend on this tile provider.
    'map_tile_url' => env('LOCATION_MAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'map_tile_attribution' => env('LOCATION_MAP_TILE_ATTRIBUTION', '© OpenStreetMap contributors'),
];
