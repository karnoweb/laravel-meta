<?php

declare(strict_types=1);

return [
    'tenant' => [
        'enabled' => false,
        'resolver' => null,
    ],

    'max_key_length' => 120,

    'max_string_length' => 512,

    'max_json_bytes' => 1048576,

    /*
    | When true, a hard delete or forceDelete() removes that owner's meta
    | inside the active tenant. Soft delete never purges. Default is off.
    */
    'purge_on_force_delete' => false,
];
