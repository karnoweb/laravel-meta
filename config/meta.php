<?php

declare(strict_types=1);

return [
    'branch' => [
        'enabled' => false,
        'resolver' => null,
    ],

    /*
    | These ceilings match the columns. A larger config value is rejected.
    | max_key_length and max_string_length count Unicode characters.
    | max_json_bytes counts bytes of the encoded JSON document.
    */
    'max_key_length' => 120,

    'max_string_length' => 512,

    'max_json_bytes' => 1048576,

    /*
    | When true, a hard delete or forceDelete() removes that owner's meta
    | inside the active branch. Soft delete never purges. Default is off.
    */
    'purge_on_force_delete' => false,
];
