<?php

namespace Ymigval\ModelCache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class CacheableBuilder extends Builder
{
    /**
     * The number of minutes to cache the query.
     *
     * @var int
     */
    protected $cacheMinutes;

    /**
     * Execute the query and get the first result from the cache.
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Model|object|static|null
     */
    public function firstFromCache($columns = ['*'])
    {
        $results = $this->take(1)->getFromCache($columns);

        return count($results) > 0 ? $results->first() : null;
    }

    /**
     * Execute the query and get the results from the cache.
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFromCache($columns = ['*'])
    {
        $minutes = $this->cacheMinutes ?: config('model-cache.cache_duration', 60);
        $cacheKey = $this->getCacheKey($columns);
        $cacheTags = $this->getCacheTags();
        $cache = $this->getCacheDriver();

        if ($cacheTags && method_exists($cache, 'tags')) {
            return $cache->tags($cacheTags)->remember($cacheKey, $minutes * 60, function () use ($columns) {
                return $this->getWithoutCache($columns);
            });
        }

        return $cache->remember($cacheKey, $minutes * 60, function () use ($columns) {
            return $this->getWithoutCache($columns);
        });
    }

    /**
     * Set the cache duration.
     *
     * @param int $minutes
     * @return $this
     */
    public function remember($minutes)
    {
        $this->cacheMinutes = $minutes;

        return $this;
    }

    /**
     * Get a unique cache key for the complete query.
     *
     * @param array $columns
     * @return string
     */
    public function getCacheKey($columns = ['*'])
    {
        $key = [
            config('model-cache.cache_key_prefix', 'model_cache_'),
            $this->toSql(),
            serialize($this->getBindings()),
            serialize($columns),
        ];

        return md5(implode('|', $key));
    }

    /**
     * Get the cache tags for the query.
     *
     * @return array
     */
    protected function getCacheTags()
    {
        return [
            'model_cache',
            get_class($this->model),
            $this->model->getTable()
        ];
    }

    /**
     * Get the models without cache.
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getWithoutCache($columns = ['*'])
    {
        return parent::get($columns);
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
            return Cache::store($cacheStore);
        }

        return Cache::store();
    }
}
