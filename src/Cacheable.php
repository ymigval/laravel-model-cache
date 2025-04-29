<?php

namespace YMigVal\LaravelModelCache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * @deprecated This trait is deprecated and will be removed in a future version.
 * Use the HasCachedQueries trait instead.
 */
trait Cacheable
{
    /**
     * Boot the trait.
     *
     * @return void
     * @deprecated Use HasCachedQueries instead
     */
    public static function bootCacheable()
    {
        trigger_error(
            'The Cacheable trait is deprecated. Use HasCachedQueries instead.',
            E_USER_DEPRECATED
        );
        
        // Flush the cache when a model is created
        static::created(function ($model) {
            $model->flushModelCache();
        });

        // Flush the cache when a model is updated
        static::updated(function ($model) {
            $model->flushModelCache();
        });

        // Flush the cache when a model is deleted
        static::deleted(function ($model) {
            $model->flushModelCache();
        });

        // Flush the cache when a model is restored
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                $model->flushModelCache();
            });
        }
    }

    /**
     * Extend the query builder with the remember method.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     * @deprecated Use HasCachedQueries instead
     */
    public function scopeCached($query)
    {
        trigger_error(
            'The Cacheable trait is deprecated. Use HasCachedQueries instead.',
            E_USER_DEPRECATED
        );
        
        if (!$this->shouldCache()) {
            return $query;
        }

        return $query->macro('remember', function (Builder $query, $duration = null) {
            $duration = $duration ?: config('model-cache.cache_duration', 60);
            $cacheKey = $this->generateCacheKey($query);

            $cacheTags = $this->generateCacheTags();

            $cache = $this->getCacheDriver();

            if ($cacheTags && method_exists($cache, 'tags')) {
                return $cache->tags($cacheTags)->remember($cacheKey, $duration * 60, function () use ($query) {
                    return $query->get();
                });
            }

            return $cache->remember($cacheKey, $duration * 60, function () use ($query) {
                return $query->get();
            });
        });
    }

    /**
     * Flush the cache for this model.
     *
     * @return void
     * @deprecated Use HasCachedQueries instead
     */
    public function flushModelCache()
    {
        $cache = $this->getCacheDriver();
        $cacheTags = $this->generateCacheTags();

        if ($cacheTags && method_exists($cache, 'tags')) {
            $cache->tags($cacheTags)->flush();
        }
    }

    /**
     * Generate a unique cache key for the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return string
     * @deprecated Use HasCachedQueries instead
     */
    protected function generateCacheKey(Builder $query)
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        
        return config('model-cache.cache_key_prefix', 'model_cache_') . 
               md5($sql . serialize($bindings) . static::class);
    }

    /**
     * Generate cache tags for the model.
     *
     * @return array
     * @deprecated Use HasCachedQueries instead
     */
    protected function generateCacheTags()
    {
        return [
            'model_cache',
            static::class,
            $this->getTable()
        ];
    }

    /**
     * Get the cache driver to use.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     * @deprecated Use HasCachedQueries instead
     */
    protected function getCacheDriver()
    {
        $cacheStore = config('model-cache.cache_store');

        if ($cacheStore) {
            return Cache::store($cacheStore);
        }

        return Cache::store();
    }

    /**
     * Determine if caching is enabled.
     *
     * @return bool
     * @deprecated Use HasCachedQueries instead
     */
    protected function shouldCache()
    {
        return config('model-cache.enabled', true);
    }
}
