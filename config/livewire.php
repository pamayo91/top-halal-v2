<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payload guards
    |--------------------------------------------------------------------------
    |
    | Long-form editorial HTML can legitimately contain nested lists imported
    | from legacy content. RichEditor serialises that structure as a deeply
    | nested Livewire property path, so retain the protection while allowing
    | this supported editorial use case.
    |
    */
    'payload' => [
        'max_nesting_depth' => 20,
    ],
];
