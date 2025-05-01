# Laravel Model Cache

A simple and efficient caching solution for Laravel Eloquent models.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ymigval/laravel-model-cache.svg?style=flat-square)](https://packagist.org/packages/ymigval/laravel-model-cache)
[![Total Downloads](https://img.shields.io/packagist/dt/ymigval/laravel-model-cache.svg?style=flat-square)](https://packagist.org/packages/ymigval/laravel-model-cache)
[![License](https://img.shields.io/packagist/l/ymigval/laravel-model-cache.svg?style=flat-square)](LICENSE.md)

## Introduction

Laravel Model Cache provides a powerful way to cache your Eloquent query results. It transparently integrates with
Laravel's query builder system to automatically cache all query results, with zero changes to your existing query
syntax. The package intelligently handles cache invalidation when models are created, updated, deleted, or restored.

## Features

- **Deep Integration**: Replaces Laravel's query builder with a cache-aware version
- **Transparent Caching**: All query methods (`get()`, `first()`, etc.) automatically use cache
- **Explicit Control**: Additional methods for when you want to be explicit about caching
- **Automatic Invalidation**: Cache is cleared when models are created, updated, deleted, or restored
- **Full Tag Support**: Works with Laravel's built-in cache tagging system
- **Performance Optimized**: Dramatically reduces database queries for read-heavy applications
- **Drop-in Solution**: Compatible with existing Laravel query builder syntax
- **Highly Configurable**: Easy to customize cache duration, store, and behavior

## Requirements

- PHP ^7.4, ^8.0, ^8.1, ^8.2, or ^8.3
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
     CACHE_STORE=redis
     
     REDIS_CLIENT=phpredis  # or predis
     REDIS_HOST=127.0.0.1
     REDIS_PASSWORD=null
     REDIS_PORT=6379
```

2. **Memcached**
   - Another good option with tag support
   - Configure in `.env`:

``` 
     CACHE_STORE=memcached
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
     CACHE_STORE=database
```

4. **File** and **Array** drivers do not support tags, but the package includes fallback mechanisms that make them
   compatible:
   - These drivers will work for basic caching functionality
   - When using these drivers, cache invalidation will clear the entire cache rather than just specific model entries
   - While not as efficient as tag-supporting drivers, they can be used in development or when other drivers are not
     available
   - Configure in `.env`:

``` 
     CACHE_STORE=file
```

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

Add the trait to any Eloquent model you want to cache:

```php
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

## How Caching Works

This package takes a unique approach to model caching by **replacing the standard Eloquent query builder** with a
cache-aware version. The `HasCachedQueries` trait overrides the `newEloquentBuilder()` method to return a
`CacheableBuilder` instance instead of the standard builder.

### Technical Details:

1. **Query Builder Replacement**: When you add the trait to a model, all queries for that model will use the custom
   builder
2. **Automatic Caching**: All standard Eloquent query methods (`get()`, `first()`, etc.) automatically check cache first
3. **Cache Key Generation**: Unique cache keys are created based on the SQL query, bindings, and model
4. **Event-Based Invalidation**: Cache is automatically cleared when models are created, updated, deleted, or restored

## Cache Usage Options

The package offers two complementary approaches to caching:

### 1. Implicit Caching (Standard Eloquent Methods)

When your model uses the `HasCachedQueries` trait, standard Eloquent methods automatically use the cache:

```php
// This automatically uses the cache with default duration
$posts = Post::where('published', true)->get();

// This also uses the cache with default duration
$post = Post::where('id', 1)->first();

// Add custom cache duration
$posts = Post::where('status', 'active')->remember(60)->get();
```

### 2. Explicit Caching Methods

For more explicit control and code readability, use the dedicated caching methods:

```php
// Explicitly get results from cache (or store in cache if not present)
$posts = Post::where('published', true)->getFromCache();

// Explicitly get first result from cache
$post = Post::where('id', 1)->firstFromCache();

// Set custom cache duration for a specific query
$posts = Post::where('status', 'active')->remember(30)->getFromCache();
```

Both approaches produce the same result - they check the cache first and only hit the database if needed.

## Choosing Between Implicit and Explicit Caching

Both approaches have advantages depending on your use case:

| Approach                        | When to Use                                 | Benefits                                                                                         |
|---------------------------------|---------------------------------------------|--------------------------------------------------------------------------------------------------|
| **Implicit** (`get()`)          | For seamless integration into existing code | - No code changes needed<br>- Transparent performance boost<br>- Works with existing code        |
| **Explicit** (`getFromCache()`) | When you want caching to be obvious         | - Self-documenting code<br>- Clearer for team members<br>- Highlights performance considerations |

### Performance Comparison

There is no performance difference between the two approaches - both implementations use the same underlying caching
mechanism. The choice is purely about code readability and developer preference.

## Advanced Caching Strategies

### Using Query Scopes with Caching

Combine Laravel scopes with cache settings for reusable cached queries:

```php
// In your model
public function scopePopular($query)
{
    return $query->where('views', '>', 1000)
                ->orderBy('views', 'desc')
                ->remember(60); // Cache popular posts for 1 hour
}

// In your controller - both work the same
$posts = Post::popular()->get(); // Implicit
$posts = Post::popular()->getFromCache(); // Explicit
```

### Conditional Caching

Dynamically decide whether to cache based on conditions:

```php
$minutes = $user->isAdmin() ? 5 : 60; // Less cache time for admins who need fresh data
$posts = Post::latest()->remember($minutes)->get();
```

### Selective Caching with Relations

Cache only specific relations:

```php
// Cache the posts but load comments fresh every time
$posts = Post::with(['comments' => function($query) {
    $query->withoutCache(); // Skip cache for this relation
}])->remember(30)->get();
```

## Manually Clearing Cache Using Console Commands

Laravel Model Cache includes built-in Artisan commands to easily clear the cache for your models from the command line.
This feature is especially useful when you need to manually invalidate cache during deployments or when troubleshooting.

### Available Cache Clearing Commands

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
2. Call the `flushModelCache()` method or clear the cache manually
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
   - The command will display a warning and ask for confirmation before proceeding with a full cache clear
   - This is necessary because without tag support, it's not possible to selectively clear only model-related cache

## Programmatic Cache Clearing

You can also clear the cache programmatically in your application code:

``` php
// Clear cache for a single model instance
$user = User::find(1);
$user->flushCache();

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
- When dealing with very large datasets, consider paginating results to reduce cache size
- Monitor your cache storage usage regularly when implementing on large tables

## Using ModelRelationships Trait

The `ModelRelationships` trait provides enhanced support for cache invalidation when working with Eloquent
relationships. It automatically flushes the cache when relationship operations (like attaching, detaching, or syncing
pivot records) are performed.

### Implementation in Models

Add both traits to your model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use YMigVal\LaravelModelCache\HasCachedQueries;
use YMigVal\LaravelModelCache\ModelRelationships;

class Post extends Model
{
    use HasCachedQueries, ModelRelationships;
    
    // Your relationship methods...
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
}
```

### Using Helper Methods for Relationships

The trait provides convenient methods to manipulate relationships while ensuring the cache is properly invalidated:

```php
// Sync a belongsToMany relationship and flush cache
$post->syncRelationshipAndFlushCache('tags', [1, 2, 3]);

// Attach records to a belongsToMany relationship and flush cache
$post->attachRelationshipAndFlushCache('tags', [4, 5], ['added_by' => 'admin']);

// Detach records from a belongsToMany relationship and flush cache
$post->detachRelationshipAndFlushCache('tags', [1, 3]);
```

### Automatic Cache Invalidation

The trait also registers event listeners that automatically flush the cache when Laravel's relationship methods are
used:

```php
// These operations will automatically flush the cache
$post->tags()->attach(1);
$post->tags()->detach([2, 3]);
$post->tags()->sync([1, 4, 5]);
$post->tags()->updateExistingPivot(1, ['featured' => true]);
```

This ensures that your cached queries always reflect the current state of your model relationships.

## Troubleshooting

### Cache Not Being Used

If your queries are not being cached:

1. Verify that your model correctly uses the `HasCachedQueries` trait
2. Check that you're using a compatible cache driver (Redis, Memcached, Database)
3. Make sure that `model-cache.enabled` is set to `true` in your configuration
4. Temporarily add logging to verify if cache hits or misses are occurring:
   ```php
   // Add this in your controller
   if (Cache::has($cacheKey)) {
       Log::info('Cache hit for key: ' . $cacheKey);
   } else {
       Log::info('Cache miss for key: ' . $cacheKey);
   }
   ```

### Cache Not Being Invalidated

If old data persists in the cache after updates:

1. Make sure your model is correctly firing create/update/delete events
2. Check if you're using query builder methods that bypass model events (`DB::table()->update()`)
3. Try manually clearing the cache to test: `YourModel::flushModelCache()`
4. Verify that your cache driver correctly supports tags if you're using them

## Frequently Asked Questions

### Q: Why use this package instead of Laravel's built-in caching?

A: This package provides automatic cache invalidation, tag-based cache management, and seamless integration with
Eloquent with minimal code changes.

### Q: Does this work with soft deleted models?

A: Yes, the package respects Laravel's soft delete functionality and will cache accordingly.

### Q: What's the difference between `get()` and `getFromCache()`?

A: Functionally they are identical when using this package - both check cache first. The difference is syntax preference
and code readability.

### Q: Can I still use regular database queries when needed?

A: Yes, you can bypass the cache using:

```php
$freshData = YourModel::withoutCache()->get();
```

### Q: Does this work with relationships?

A: Yes, caching works with eager-loaded relationships and regular relationship queries.

## Migrating from Other Caching Solutions

### From Manual Laravel Cache

If you're currently using Laravel's Cache facade manually:

```php
// Old approach with manual caching
$cacheKey = 'posts_' . md5($query);
$posts = Cache::remember($cacheKey, 60, function() use ($query) {
    return Post::where(...)->get();
});

// New approach with this package
$posts = Post::where(...)->remember(60)->get();
```

### From Other Cache Packages

If you're using another caching package:

1. Replace the other package's trait with `HasCachedQueries`
2. Remove manual cache key generation code
3. Replace custom cache retrieval methods with standard Eloquent methods or the explicit `getFromCache()` methods
4. Remove manual cache invalidation code (this package handles it automatically)