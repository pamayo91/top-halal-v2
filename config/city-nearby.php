<?php

return [
    // Used only by the explicit maintenance command. Public requests read the local table.
    'reference_source_url' => env('CITY_REFERENCE_POINTS_SOURCE_URL', 'https://geo.api.gouv.fr/communes'),
];
