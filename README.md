# Laravel Model Cache

A simple and efficient caching solution for Laravel Eloquent models.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ymigval/laravel-model-cache.svg?style=flat-square)](https://packagist.org/packages/ymigval/laravel-model-cache)
[![Total Downloads](https://img.shields.io/packagist/dt/ymigval/laravel-model-cache.svg?style=flat-square)](https://packagist.org/packages/ymigval/laravel-model-cache)
[![License](https://img.shields.io/packagist/l/ymigval/laravel-model-cache.svg?style=flat-square)](LICENSE.md)

## Introduction

Laravel Model Cache provides a simple way to cache your Eloquent query results. It automatically handles cache
invalidation when models are created, updated, deleted, or restored.

## Features

- Simple integration with Eloquent models through a trait
- Automatic cache invalidation when models are created, updated, deleted, or restored
- Works with Laravel's built-in cache system using tags
- Improves application performance by reducing database queries
- Compatible with Laravel's query builder syntax
- Easy to configure and customize

## Requirements

- PHP 7.3 or higher
- Laravel 8.x, 9.x, 10.x, 11.x, or 12.x

## Installation

Install the package via Composer:

```shell script
composer require ymigval/laravel-model-cache
```

## Publish the Configuration File (optional)

To customize the package configuration, publish the configuration file:

```shell script
php artisan vendor:publish --provider="YMigVal\LaravelModelCache\ModelCacheServiceProvider" --tag="config"
```

This creates with the following customizable options: `config/model-cache.php`

- `cache_duration`: Default cache TTL in minutes (default: 60)
- `cache_key_prefix`: Prefix for all cache keys (default: 'model_cache_')
- `cache_store`: Specific cache store to use for model caching
- `enabled`: Global toggle to enable/disable the cache functionality

## Service Provider Registration

The package comes with Laravel package auto-discovery for Laravel 5.5+. For older versions, register the service
provider in : `config/app.php`

```php
  'providers' => [
      // Other service providers...
      YMigVal\LaravelModelCache\ModelCacheServiceProvider::class,
  ],
```

## Cache Driver Configuration

### Supported Cache Drivers

This package uses Laravel's tagging system for cache, so you must use a cache driver that supports tags:

1. **Redis** (Recommended)
    - Offers excellent performance and tag support
    - Configure in `.env`:

``` 
     CACHE_DRIVER=redis
     REDIS_CLIENT=phpredis  # or predis
     REDIS_HOST=127.0.0.1
     REDIS_PASSWORD=null
     REDIS_PORT=6379
```

2. **Memcached**
    - Another good option with tag support
    - Configure in `.env`:

``` 
     CACHE_DRIVER=memcached
     MEMCACHED_HOST=127.0.0.1
     MEMCACHED_PORT=11211
```

3. **Database**
    - Supports tags but slower than Redis/Memcached
    - Requires cache table creation:

``` 
     php artisan cache:table
     php artisan migrate
```

- Configure in `.env`:

``` 
     CACHE_DRIVER=database
```

4. **File** and **Array** drivers **DO NOT** support tags and will not work correctly with this package.

## Optional Configuration in Config File

The file allows you to adjust: `config/model-cache.php`

- Default cache Time-To-Live (TTL)
- Globally enable/disable caching
- Prefix for all cache keys
- Specific cache driver for model caching

### Cache Store Selection

You can specify which cache store to use specifically for model caching in the config file:

``` php
// config/model-cache.php
'cache_store' => env('MODEL_CACHE_STORE', 'redis'),
```

This allows you to use a different cache store for models than your application's default cache store.

## Implementation in Models

Add the trait to any Eloquent model you want to cache: `HasCachedQueries`

``` php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use YMigVal\LaravelModelCache\HasCachedQueries;

class Post extends Model
{
    use HasCachedQueries;
    
    // Optional: Override the default cache duration for this model
    protected $cacheMinutes = 120; // 2 hours
}
```

## Cache Usage

Once configured, the package automatically:

1. Caches query results when using methods like `get()`, `first()`, etc.
2. Invalidates cache when models are created, updated, deleted, or restored
3. Builds unique cache keys based on the SQL query, bindings, and selected columns

### Methods Available:

``` php
// Get results from cache (or store in cache if not present)
$posts = Post::where('published', true)->getFromCache();

// Get first result from cache
$post = Post::where('id', 1)->firstFromCache();

// Set custom cache duration for a specific query
$posts = Post::where('status', 'active')->remember(30)->getFromCache();

```

# Manually Clearing Cache Using Console Commands

Laravel Model Cache includes built-in Artisan commands to easily clear the cache for your models from the command line.
This feature is especially useful when you need to manually invalidate cache during deployments or when troubleshooting.

## Available Cache Clearing Commands

The package registers the following Artisan command:

``` shell
php artisan mcache:flush {model?}
```

The `{model?}` parameter is optional. When provided, it should be the fully qualified class name of the model you want
to clear the cache for.

## Command Usage Examples

### Clear Cache for a Specific Model

To clear the cache for a specific model, provide the fully qualified class name:

``` shell
# Clear cache for User model
php artisan mcache:flush "App\Models\User"

# Clear cache for Product model
php artisan mcache:flush "App\Models\Product"
```

The command will:

1. Instantiate the model class
2. Call the `flushModelCache()` method or clear the cache manually if the method doesn't exist
3. Display a confirmation message when complete

### Clear Cache for All Models

To clear the cache for all models at once, run the command without any arguments:

``` shell
php artisan mcache:flush
```

This will clear all cache entries tagged with 'model_cache', effectively clearing the cache for all models that use the
package.

## How the Command Works

1. **Tag-Based Clearing**
    - The command uses cache tags to efficiently clear only relevant cache entries
    - Tags are structured as ['model_cache', ModelClassName, TableName]
    - This is efficient as it only removes cache related to the specified model

2. **Fallback for Non-Tag-Supporting Drivers**
    - For cache drivers that don't support tags (File, Database), the command falls back to clearing all cache
    - The command will display a warning if your cache driver doesn't support tags

## Programmatic Cache Clearing

You can also clear the cache programmatically in your application code:

``` php
// Clear cache for a single model instance
$user = User::find(1);
$user->flushModelCache();

// Clear cache for an entire model class
User::flushModelCache();
```

## When to Use Cache Clearing Commands

Consider manually clearing cache in these situations:

1. **Deployments**: After deploying new code that might make cached data obsolete
2. **Data Imports**: After bulk importing data that bypasses model events
3. **Schema Changes**: After changing database schema that affects model structures
4. **Debugging**: When troubleshooting issues that might be related to stale cache

## Performance Considerations

- Consider setting different cache durations for different models based on how frequently they change
- Use cache tags wisely to avoid invalidating too much cache at once
- For high-traffic applications, consider implementing a cache warming strategy