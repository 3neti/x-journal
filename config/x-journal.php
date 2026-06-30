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

    'idempotency' => [
        'enabled' => true,
    ],

    'visibility' => [
        'role_profiles' => [],
        'event_profiles' => [],
    ],

    'verification' => [
        'base_url' => null,
        'path' => '/verify',
        'token_secret' => null,
        'token_ttl_minutes' => 0,
    ],

    'sinks' => [
        'monolog' => [
            'enabled' => false,
            'channel' => 'default',
            'message' => 'execution.journal.recorded',
        ],
        'null' => [
            'enabled' => false,
        ],
    ],
];
