# Changelog

All notable changes to `laravel-model-cache` will be documented in this file.

## [1.1.2] - 2025-05-21

### Fixed
- Fixed implementation of the `ModelRelationships` trait to properly handle BelongsToMany operations. Replaced the event-based approach (which was relying on non-existent Laravel events) with a custom BelongsToMany relationship class that flushes the cache after attach, detach, sync, syncWithoutDetaching, and updateExistingPivot operations.
- Updated `CachingBelongsToMany` class to properly extend Laravel's BelongsToMany class and maintain the relationship contract. This resolves the "must return a relationship instance" error when accessing relationship properties after operations like attach() and detach().

## [1.1.1] - 2025-05-19

### Fixed
- Fixed implementation of configuration for enabling or disabling cache: The `model-cache.enabled` configuration parameter is now properly checked in the HasCachedQueries trait, ensuring that when cache is disabled via configuration, the standard Eloquent builder is used instead of the cache-enabled version.


## [1.1.0] - 2025-05-13

### Added
- Added support for custom cache prefix per model via `$cachePrefix` property
- Added `ModelRelationships` trait to support cache invalidation for Eloquent relationship operations
- Support for flushing cache on belongsToMany relationship events (saved, attached, detached, synced, updated)
- New helper methods for relationship operations with automatic cache flushing: `syncRelationshipAndFlushCache()`, `attachRelationshipAndFlushCache()`, `detachRelationshipAndFlushCache()`
- Debug logging for relationship-triggered cache flush operations when debug_mode is enabled

### Fixed
- Fixed issue with custom cache minutes (`$cacheMinutes`) definition at the model level
- Improved cache invalidation when working with Eloquent relationships
- Better handling of model relationship events to ensure cache consistency



## [1.0.1] - 2025-05-04

### Fixed

- Add debug_mode config check for logger usage in model cache: This ensures logging of cache flush operations only
  occurs when debug_mode is explicitly enabled in the configuration. It reduces unnecessary log entries in production
  environments while retaining detailed logs for debugging purposes.

## [1.0.0] - 2025-05-01

### Added
- Initial implementation of Eloquent model caching system
- `HasCachedQueries` trait to enable caching on any model
- Transparent integration with Laravel's query builder
- Explicit methods for cache control (`getFromCache()`, `firstFromCache()`)
- Automatic cache invalidation when models are created, updated, deleted, or restored
- Full cache tag support
- `mcache:flush` Artisan command for manual cache clearing
- Customizable configuration for cache duration, key prefix, and cache store
- Compatibility with Laravel 8.x, 9.x, 10.x, 11.x, and 12.x
- Support for PHP 7.4, 8.0, 8.1, 8.2, and 8.3
