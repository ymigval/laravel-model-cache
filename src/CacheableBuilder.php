<?php

namespace YMigVal\LaravelModelCache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
     * @return \Illuminate\Database\Eloquent\Model|\stdClass|static|null
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
     * Disable caching for this query.
     *
     * @return $this
     */
    public function withoutCache()
    {
        $this->cacheMinutes = 0;
        
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
            $this->model->getTable(),
            $this->toSql(),
            serialize($this->getBindings()),
            serialize($columns),
            app()->getLocale(), // Add locale for multilingual sites
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
     * @return Collection
     */
    protected function getWithoutCache($columns = ['*'])
    {
        return parent::get($columns);
    }

    /**
     * Override the get method to automatically use cache.
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($columns = ['*'])
    {
        // Only use cache if cacheMinutes is not set to 0
        if (isset($this->cacheMinutes) && $this->cacheMinutes === 0) {
            return $this->getWithoutCache($columns);
        }

        return $this->getFromCache($columns);
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
            return Cache::store($cacheStore);
        }

        return Cache::store();
    }

    /**
     * Clear the cache for this model.
     *
     * @return bool
     */
    public function flushCache()
    {
        $cacheTags = $this->getCacheTags();
        $cache = $this->getCacheDriver();

        if ($cacheTags && method_exists($cache, 'tags')) {
            return $cache->tags($cacheTags)->flush();
        }

        // When not using tags, we can't selectively flush the cache
        // This is why using cache tags is recommended
        return false;
    }

    /**
     * Paginate the given query with caching support.
     *
     * @param int|null $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginateFromCache($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: \Illuminate\Pagination\Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $cacheKey = $this->getCacheKey([
            'paginate',
            $perPage,
            $pageName,
            $page,
            serialize($columns)
        ]);

        $minutes = $this->cacheMinutes ?: config('model-cache.cache_duration', 60);
        $cacheTags = $this->getCacheTags();
        $cache = $this->getCacheDriver();

        $callback = function () use ($perPage, $columns, $pageName, $page) {
            return parent::paginate($perPage, $columns, $pageName, $page);
        };

        if ($cacheTags && method_exists($cache, 'tags')) {
            return $cache->tags($cacheTags)->remember($cacheKey, $minutes * 60, $callback);
        }

        return $cache->remember($cacheKey, $minutes * 60, $callback);
    }
}
