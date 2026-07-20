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

    /*
    |--------------------------------------------------------------------------
    | Heuristic fallback
    |--------------------------------------------------------------------------
    |
    | When a PURL is not in the curated catalog, optionally derive a best-effort
    | CPE from the PURL's namespace and name (e.g. pkg:composer/vendor/name ->
    | cpe:2.3:a:vendor:name:...). This is a guess that may match an NVD record,
    | not an authoritative mapping — off by default. Any resolver call can also
    | override this per-invocation via its $heuristic argument.
    |
    */
    'heuristic_fallback' => env('PURL2CPE_HEURISTIC_FALLBACK', false),

];
