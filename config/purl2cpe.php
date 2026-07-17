<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    |
    | The connection the purl_cpe_mappings table lives on. Null uses the
    | application's default connection.
    |
    */
    'connection' => env('PURL2CPE_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Table name
    |--------------------------------------------------------------------------
    */
    'table' => env('PURL2CPE_TABLE', 'purl_cpe_mappings'),

    /*
    |--------------------------------------------------------------------------
    | Upstream database URL
    |--------------------------------------------------------------------------
    |
    | The scanoss/purl2cpe SQLite archive used by `purl2cpe:sync` to rebuild
    | the mappings from scratch. The package already ships a reduced copy that
    | `purl2cpe:import` loads, so syncing is only needed to pull fresh data.
    |
    */
    'db_url' => env('PURL2CPE_DB_URL', 'https://github.com/scanoss/purl2cpe/raw/main/purl2cpe.db.zip'),

];
