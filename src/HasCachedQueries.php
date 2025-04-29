<?php

namespace Ymigval\ModelCache;

trait HasCachedQueries
{
    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Ymigval\ModelCache\CacheableBuilder
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
     * Flush the cache for this model.
     *
     * @return void
     */
    public function flushModelCache()
    {
        $cache = $this->getCacheDriver();
        $tags = [
            'model_cache',
            static::class,
            $this->getTable()
        ];

        if (method_exists($cache, 'tags')) {
            $cache->tags($tags)->flush();
        }
    }

    /**
     * Get the cache driver to use.
     *
     * @return \Illuminate\Contracts\Cache\Repository
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
