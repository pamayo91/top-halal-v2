<?php
return ['uploads_path' => env('LEGACY_UPLOADS_PATH'), 'additional_uploads_paths' => array_filter(explode('|', (string) env('LEGACY_ADDITIONAL_UPLOADS_PATHS', ''))), 'disk' => env('MEDIA_DISK', 'local'), 'variants' => [['name'=>'card','width'=>480],['name'=>'content','width'=>960],['name'=>'hero','width'=>1440]]];
