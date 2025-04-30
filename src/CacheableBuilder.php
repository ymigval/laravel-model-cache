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

        // Check if the cache driver supports tags
        $supportsTags = $this->supportsTags($cache);

        if ($cacheTags && $supportsTags) {
            return $cache->tags($cacheTags)->remember($cacheKey, $minutes * 60, function () use ($columns) {
                return $this->getWithoutCache($columns);
            });
        }

        // Fallback for drivers that don't support tagging
        return $cache->remember($cacheKey, $minutes * 60, function () use ($columns) {
            return $this->getWithoutCache($columns);
        });
    }

    /**
     * Check if the cache driver supports tags.
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
        // This is the prefix defined in our package config
        $configPrefix = config('model-cache.cache_key_prefix', 'model_cache_');

        // Create unique components for our key
        $keyComponents = [
            $configPrefix,
            $this->model->getTable(),
            $this->toSql(),
            serialize($this->getBindings()),
            serialize($columns),
            app()->getLocale(), // Add locale for multilingual sites
        ];

        // Create a hash from all components
        $uniqueKey = md5(implode('|', $keyComponents));

        // Return only the unique hash - Laravel will handle adding its own prefix
        return $uniqueKey;
    }

    /**
     * Flush the cache for this specific query.
     *
     * This allows flushing only the cache for a specific query pattern like:
     * Model::where('condition', $value)->flushCache();
     *
     * @param array $columns
     * @return bool
     */
    public function flushQueryCache($columns = ['*'])
    {
        try {
            // Get the specific key for this query
            $cacheKey = $this->getCacheKey($columns);
            $cache = $this->getCacheDriver();

            // Log the operation if logger is available
            if (function_exists('logger')) {
                logger()->info("Flushing specific query cache: " . $cacheKey);
                logger()->debug("SQL: " . $this->toSql());
                logger()->debug("Bindings: " . json_encode($this->getBindings()));
            }

            // Forget this specific key
            $result = $cache->forget($cacheKey);

            // Also try with tags if supported
            $cacheTags = $this->getCacheTags();
            if ($cacheTags && $this->supportsTags($cache)) {
                try {
                    // Generate tags specific to this query to be more precise
                    $queryTags = $cacheTags;
                    $queryTags[] = md5($this->toSql() . serialize($this->getBindings()));

                    // Attempt to flush by query-specific tags
                    $cache->tags($queryTags)->flush();
                } catch (\Exception $e) {
                    // If this fails, we already tried the direct key removal above
                    if (function_exists('logger')) {
                        logger()->debug("Could not flush by query tags: " . $e->getMessage());
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->error("Error flushing query cache: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Alias for flushQueryCache for backward compatibility with existing code.
     *
     * @param array $columns
     * @return bool
     */
    public function flushCache($columns = ['*'])
    {
        return $this->flushQueryCache($columns);
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
        try {
            $cacheStore = config('model-cache.cache_store');

            if ($cacheStore) {
                return Cache::store($cacheStore);
            }

            return Cache::store();
        } catch (\Exception $e) {
            // If there's an issue with the configured cache driver,
            // fall back to the default driver
            return Cache::store(config('cache.default'));
        }
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

        try {
            // Check if the cache driver supports tags
            if ($cacheTags && $this->supportsTags($cache)) {
                return $cache->tags($cacheTags)->remember($cacheKey, $minutes * 60, $callback);
            }
        } catch (\BadMethodCallException $e) {
            // If tags are not supported, we'll fall through to the default behavior
        }

        return $cache->remember($cacheKey, $minutes * 60, $callback);
    }
}
