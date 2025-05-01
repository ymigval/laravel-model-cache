<?php

namespace YMigVal\LaravelModelCache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Query\Builder;

trait HasCachedQueries
{
    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param Builder $query
     * @return CacheableBuilder
     */
    public function newEloquentBuilder($query)
    {
        return new CacheableBuilder($query);
    }

    /**
     * Boot the trait.
     *
     * This method registers event handlers for individual model operations that trigger Eloquent events:
     * - created: When a new model is created via Model::create() or $model->save() on a new instance
     * - updated: When an existing model is updated via $model->save() or $model->update()
     * - saved: When a model is created or updated via $model->save()
     * - deleted: When a model is deleted via $model->delete()
     * - restored: When a soft-deleted model is restored via $model->restore()
     *
     * NOTE: Mass operations that don't retrieve models first (like Model::where(...)->update() or
     * Model::where(...)->delete()) do not trigger these events. For these operations, the CacheableBuilder
     * class overrides methods like update(), delete(), insert(), insertGetId(), insertOrIgnore(),
     * updateOrInsert(), upsert(), truncate(), increment(), decrement(), forceDelete(), and restore()
     * to ensure cache is properly invalidated in all scenarios.
     *
     * @return void
     */
    public static function bootHasCachedQueries()
    {
        // Flush the cache when a model is created
        static::created(function ($model) {
            static::flushModelCache();
            if (function_exists('logger')) {
                logger()->info("Cache flushed after creation for model: " . get_class($model));
            }
        });

        // Flush the cache when a model is updated
        static::updated(function ($model) {
            static::flushModelCache();
            if (function_exists('logger')) {
                logger()->info("Cache flushed after update for model: " . get_class($model));
            }
        });

        // Flush the cache when a model is saved
        static::saved(function ($model) {
            static::flushModelCache();
            if (function_exists('logger')) {
                logger()->info("Cache flushed after update for model: " . get_class($model));
            }
        });

        // Flush the cache when a model is deleted
        static::deleted(function ($model) {
            static::flushModelCache();
            if (function_exists('logger')) {
                logger()->info("Cache flushed after deletion for model: " . get_class($model));
            }
        });

        // Flush the cache when a model is restored
        static::registerModelEvent('restored', function ($model) {
            static::flushModelCache();
            if (function_exists('logger')) {
                logger()->info("Cache flushed after restoration for model: " . get_class($model));
            }
        });
    }

    /**
     * Static method to flush cache for the model.
     * This allows calling Model::flushModelCache() directly without an instance.
     *
     * @return bool
     */
    public static function flushModelCache()
    {
        try {
            // Get model info without creating a full instance
            $modelClass = static::class;
            $model = new static;
            $tableName = $model->getTable();

            // Get the cache driver directly
            $cacheStore = config('model-cache.cache_store');
            $cache = $cacheStore ? \Illuminate\Support\Facades\Cache::store($cacheStore) : \Illuminate\Support\Facades\Cache::store();

            // Set tags for this model
            $tags = [
                'model_cache',
                $modelClass,
                $tableName
            ];

            // Try with tags if supported
            if (method_exists($cache, 'tags') && $cache->supportsTags()) {
                try {
                    $result = $cache->tags($tags)->flush();
                    if (function_exists('logger')) {
                        logger()->info("Cache flushed statically for model: " . $modelClass);
                    }
                    return $result;
                } catch (\Exception $e) {
                    if (function_exists('logger')) {
                        logger()->error("Error flushing cache with tags for model {$modelClass}: " . $e->getMessage());
                    }
                    // Continue to fallback method if tags fail
                }
            }

            // Fallback to flush the entire cache
            $result = $cache->flush();
            if (function_exists('logger')) {
                logger()->info("Entire cache flushed for model: " . $modelClass);
            }
            return $result;

        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->error("Error in flushCacheStatic for model " . static::class . ": " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Allow flushing specific query cache when used directly in a query chain.
     * This method is intended to be used as:
     * Model::where('condition', $value)->flushCache();
     *
     * @return bool
     */
    public function scopeFlushCache($query)
    {
        if (method_exists($query, 'flushQueryCache')) {
            return $query->flushQueryCache();
        }

        // Fallback to flushing the entire model cache
        return $this->flushCache();
    }


    /**
     * Flush the cache for this model.
     *
     * @return bool
     */
    public function flushCache()
    {
        try {
            $cache = $this->getCacheDriver();
            $tags = [
                'model_cache',
                static::class,
                $this->getTable()
            ];

            // Debug info
            if (function_exists('logger')) {
                logger()->debug("Attempting to flush cache for model: " . static::class . " (Table: " . $this->getTable() . ")");
            }

            // First try with tags if supported
            if ($this->supportsTags($cache)) {
                try {
                    $result = $cache->tags($tags)->flush();
                    if (function_exists('logger')) {
                        logger()->info("Cache cleared with tags for model: " . static::class);
                    }
                    return $result;
                } catch (\Exception $e) {
                    if (function_exists('logger')) {
                        logger()->warning("Error using tags to flush cache: " . $e->getMessage() . ". Falling back to full cache clear.");
                    }
                    // Continue to fallback method
                }
            }

            // For simplicity and to ensure it actually clears the cache,
            // flush the entire cache when tags aren't supported
            $result = $cache->flush();
            if (function_exists('logger')) {
                logger()->info("Entire cache flushed for model: " . static::class);
            }
            return $result;

        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->error("Error in flushCache: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Get a static instance of the cache driver.
     * This allows static methods to use the cache without creating a full model instance.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    protected static function getStaticCacheDriver()
    {
        try {
            $cacheStore = config('model-cache.cache_store');

            if ($cacheStore) {
                return \Illuminate\Support\Facades\Cache::store($cacheStore);
            }

            return \Illuminate\Support\Facades\Cache::store();
        } catch (\Exception $e) {
            // If there's an issue with the configured cache driver,
            // fall back to the default driver
            if (function_exists('logger')) {
                logger()->error('Error getting cache driver: ' . $e->getMessage());
            }
            return \Illuminate\Support\Facades\Cache::store(config('cache.default'));
        }
    }

    /**
     * Determine if cache driver supports tags.
     *
     * @param \Illuminate\Contracts\Cache\Repository $cache
     * @return bool
     */
    protected function supportsTags($cache)
    {
        try {
            return method_exists($cache, 'tags') && $cache->supportsTags();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the cache driver to use.
     *
     * @return Repository
     */
    protected function getCacheDriver()
    {
        try {
            $cacheStore = config('model-cache.cache_store');

            if ($cacheStore) {
                return \Illuminate\Support\Facades\Cache::store($cacheStore);
            }

            return \Illuminate\Support\Facades\Cache::store();
        } catch (\Exception $e) {
            // If there's an issue with the configured cache driver,
            // fall back to the default driver
            return \Illuminate\Support\Facades\Cache::store(config('cache.default'));
        }
    }
}
