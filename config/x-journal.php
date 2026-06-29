<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Journal Connection
    |--------------------------------------------------------------------------
    |
    | The connection is reserved for the durable journal store. Null means the
    | host application's default database connection should be used.
    |
    */

    'connection' => null,

    'reference_number' => [
        'prefix' => 'ERN',
        'digits' => 9,
    ],
];
