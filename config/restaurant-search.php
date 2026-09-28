<?php

return [
    // Public proximity is intentionally fixed for now. It is an application
    // behaviour setting, not the SEO city-neighbourhood setting.
    'nearby_radius_km' => (int) env('RESTAURANT_NEARBY_RADIUS_KM', 30),
];
