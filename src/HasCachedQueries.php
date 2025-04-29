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
     * @return void
     */
    public static function bootHasCachedQueries()
    {
        // Flush the cache when a model is created
        static::created(function ($model) {
            $model->flushCache();
        });

        // Flush the cache when a model is updated
        static::updated(function ($model) {
            $model->flushCache();
        });

        // Flush the cache when a model is deleted
        static::deleted(function ($model) {
            $model->flushCache();
        });

        // Flush the cache when a model is restored
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                $model->flushCache();
            });
        }
    }

    /**
     * Flush the cache for this model.
     *
     * @return void
     * @deprecated Use flushCache() instead
     */
    public function flushModelCache()
    {
        $this->flushCache();
    }

    /**
     * Flush the cache for this model.
     *
     * @return bool
     */
    public function flushCache()
    {
        $cache = $this->getCacheDriver();
        $tags = [
            'model_cache',
            static::class,
            $this->getTable()
        ];

        if (method_exists($cache, 'tags')) {
            return $cache->tags($tags)->flush();
        }

        return false;
    }

    /**
     * Get the cache driver to use.
     *
     * @return Repository
     */
    protected function getCacheDriver()
    {
        $cacheStore = config('model-cache.cache_store');

        if ($cacheStore) {
            return \Illuminate\Support\Facades\Cache::store($cacheStore);
        }

        return \Illuminate\Support\Facades\Cache::store();
    }
}
