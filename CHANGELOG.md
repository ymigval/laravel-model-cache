# Changelog

All notable changes to `laravel-model-cache` will be documented in this file.

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
