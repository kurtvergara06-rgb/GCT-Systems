<?php

return [
    /*
    | Demo records are never seeded implicitly from APP_ENV. Enable them only
    | for a deliberate local/demo seed operation.
    */
    'enabled' => filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL),
];
