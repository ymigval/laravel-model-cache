<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Duration
    |--------------------------------------------------------------------------
    |
    | This value determines the default number of minutes to cache query results.
    |
    */
    'cache_duration' => 60,

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used for all cache keys to avoid collisions.
    |
    */
    'cache_key_prefix' => 'model_cache_',

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the cache store that gets used for storing
    | and retrieving queries. Use env('MODEL_CACHE_STORE') to specify 
    | a different store than your main application cache.
    |
    | Note: For tag support, use Redis or Memcached drivers.
    |
    */
    'cache_store' => env('MODEL_CACHE_STORE', null),

    /*
    |--------------------------------------------------------------------------
    | Enable Query Caching
    |--------------------------------------------------------------------------
    |
    | This option provides an easy way to globally enable/disable query caching.
    |
    */
    'enabled' => env('MODEL_CACHE_ENABLED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, this will log detailed information about cache keys and
    | queries being cached. Useful for troubleshooting cache-related issues.
    |
    */
    'debug_mode' => env('MODEL_CACHE_DEBUG', false),
];
