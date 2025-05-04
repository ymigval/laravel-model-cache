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
     * Create a new model instance and store it in the database.
     *
     * @param array $attributes
     * @return \Illuminate\Database\Eloquent\Model|$this
     */
    public function create(array $attributes = [])
    {
        $model = parent::create($attributes);

        // Flush cache after creating a model
        if ($model && method_exists($model, 'flushModelCache')) {
            $model->flushModelCache();
        } else {
            $this->flushQueryCache();
        }

        return $model;
    }

    /**
     * Create a new instance of the model being queried.
     *
     * @param array $attributes
     * @return \Illuminate\Database\Eloquent\Model|static
     */
    public function make(array $attributes = [])
    {
        return parent::make($attributes);
    }

    /**
     * Create a new model instance and store it in the database without mass assignment protection.
     *
     * @param array $attributes
     * @return \Illuminate\Database\Eloquent\Model|$this
     */
    public function forceCreate(array $attributes)
    {
        $model = parent::forceCreate($attributes);

        // Flush cache after force creating a model
        if ($model && method_exists($model, 'flushModelCache')) {
            $model->flushModelCache();
        } else {
            $this->flushQueryCache();
        }

        return $model;
    }

    /**
     * Get the first record matching the attributes or create it.
     *
     * @param array $attributes
     * @param array $values
     * @return \Illuminate\Database\Eloquent\Model|static
     */
    public function firstOrCreate(array $attributes = [], array $values = [])
    {
        $model = parent::firstOrCreate($attributes, $values);

        // Flush cache if model was created (doesn't exist before)
        if ($model->wasRecentlyCreated && method_exists($model, 'flushModelCache')) {
            $model->flushModelCache();
        } elseif ($model->wasRecentlyCreated) {
            $this->flushQueryCache();
        }

        return $model;
    }

    /**
     * Get the first record matching the attributes or instantiate it.
     *
     * @param array $attributes
     * @param array $values
     * @return \Illuminate\Database\Eloquent\Model|static
     */
    public function firstOrNew(array $attributes = [], array $values = [])
    {
        return parent::firstOrNew($attributes, $values);
    }

    /**
     * Save a new model and return the instance.
     *
     * @param array $attributes
     * @return \Illuminate\Database\Eloquent\Model|$this
     */
    public function save(array $attributes = [])
    {
        $instance = $this->newModelInstance($attributes);

        $instance->save();

        // Flush cache after saving
        if (method_exists($instance, 'flushModelCache')) {
            $instance->flushModelCache();
        } else {
            $this->flushQueryCache();
        }

        return $instance;
    }

    /**
     * Save a new model without mass assignment protection and return the instance.
     *
     * @param array $attributes
     * @return \Illuminate\Database\Eloquent\Model|$this
     */
    public function forceSave(array $attributes = [])
    {
        $instance = $this->newModelInstance();
        $instance->forceFill($attributes)->save();

        // Flush cache after saving
        if (method_exists($instance, 'flushModelCache')) {
            $instance->flushModelCache();
        } else {
            $this->flushQueryCache();
        }

        return $instance;
    }

    /**
     * Save a collection of models to the database.
     *
     * @param array|\Illuminate\Support\Collection $models
     * @return array|\Illuminate\Support\Collection
     */
    public function saveMany($models)
    {
        foreach ($models as $model) {
            $model->save();
        }

        // Flush cache after saving multiple models
        if (count($models) > 0) {
            $model = $models[0];
            if (method_exists($model, 'flushModelCache')) {
                $model->flushModelCache();
            } else {
                $this->flushQueryCache();
            }
        }

        return $models;
    }

    /**
     * Create multiple instances of the model.
     *
     * @param array $records
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function createMany(array $records)
    {
        $instances = new Collection();

        foreach ($records as $record) {
            $instances->push($this->create($record));
        }

        return $instances;
    }

    /**
     * Update records in the database without raising any events.
     *
     * @param array $values
     * @return int
     */
    public function updateQuietly(array $values)
    {
        $model = $this->model;

        $result = $model->withoutEvents(function () use ($values) {
            return $this->update($values);
        });

        // Still flush cache even though events aren't fired
        if ($result && method_exists($model, 'flushModelCache')) {
            $model->flushModelCache();
        } elseif ($result) {
            $this->flushQueryCache();
        }

        return $result;
    }

    /**
     * Delete records from the database without raising any events.
     *
     * @return mixed
     */
    public function deleteQuietly()
    {
        $model = $this->model;

        $result = $model->withoutEvents(function () {
            return $this->delete();
        });

        // Still flush cache even though events aren't fired
        if ($result && method_exists($model, 'flushModelCache')) {
            $model->flushModelCache();
        } elseif ($result) {
            $this->flushQueryCache();
        }

        return $result;
    }

    /**
     * Touch all of the related models for the relationship.
     *
     * @param null $column
     * @return void
     */
    public function touch($column = null)
    {
        parent::touch($column);

        // Flush cache
        if (method_exists($this->model, 'flushModelCache')) {
            $this->model->flushModelCache();
        } else {
            $this->flushQueryCache();
        }
    }

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

        // Include eager loaded relationships in the cache key
        if (count($this->eagerLoad) > 0) {
            $keyComponents[] = 'with:' . serialize(array_keys($this->eagerLoad));
        }
    
        // Create a hash from all components
        $uniqueKey = md5(implode('|', $keyComponents));
    
        // Add debug logging if enabled
        if (config('model-cache.debug_mode', false) && function_exists('logger')) {
            logger()->debug("Generated cache key: {$uniqueKey} for query: {$this->toSql()} with bindings: " . json_encode($this->getBindings()) . " and relations: " . json_encode(array_keys($this->eagerLoad)));
        }

        // Return only the unique hash - Laravel will handle adding its own prefix
        return $uniqueKey;
    }

    /**
     * Flush the cache for this specific query.
     *
     * This method is called in two primary situations:
     * 1. Explicitly by the user: Model::where('condition', $value)->flushCache();
     * 2. Automatically after mass operations like update(), delete(), etc.
     *
     * The method attempts to clear the cache in three ways, in order of specificity:
     * 1. First, it tries to remove the specific cache key for this query
     * 2. If the cache driver supports tags, it tries to flush by model-specific tags
     * 3. As a fallback, it calls the model's flushModelCache() method
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
            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Flushing specific query cache: " . $cacheKey);
                logger()->debug("SQL: " . $this->toSql());
                logger()->debug("Bindings: " . json_encode($this->getBindings()));
            }

            $success = false;

            // First try to forget this specific key
            $result = $cache->forget($cacheKey);
            if ($result) {
                $success = true;
                if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                    logger()->debug("Successfully removed specific cache key: " . $cacheKey);
                }
            }

            // Also try with tags if supported
            $cacheTags = $this->getCacheTags();
            if ($cacheTags && $this->supportsTags($cache)) {
                try {
                    // First try model-specific tags
                    $cache->tags($cacheTags)->flush();

                    // Then try query-specific tags to be even more precise
                    $queryTags = $cacheTags;
                    $queryTags[] = md5($this->toSql() . serialize($this->getBindings()));
                    $cache->tags($queryTags)->flush();

                    $success = true;
                    if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                        logger()->debug("Successfully flushed cache using tags for model: " . get_class($this->model));
                    }
                } catch (\Exception $e) {
                    // If this fails, we already tried the direct key removal above
                    if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                        logger()->debug("Could not flush by query tags: " . $e->getMessage());
                    }
                }
            }

            // If both specific key and tags failed, try to flush related model cache
            if (!$success) {
                if (method_exists($this->model, 'flushModelCache')) {
                    $this->model->flushModelCache();
                    $success = true;
                    if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                        logger()->info("Flushed entire model cache for: " . get_class($this->model));
                    }
                }
            }

            return $success || $result;
        } catch (\Exception $e) {
            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
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
    
    /**
     * Retrieve the "count" result of the query from cache.
     *
     * @param string $columns
     * @return int
     */
    public function countFromCache($columns = '*')
    {
        $cacheKey = $this->getCacheKey([
            'count',
            is_array($columns) ? implode(',', $columns) : $columns
        ]);
    
        $minutes = $this->cacheMinutes ?: config('model-cache.cache_duration', 60);
        $cacheTags = $this->getCacheTags();
        $cache = $this->getCacheDriver();
    
        $callback = function () use ($columns) {
            return parent::count($columns);
        };
    
        if ($cacheTags && $this->supportsTags($cache)) {
            return $cache->tags($cacheTags)->remember($cacheKey, $minutes * 60, $callback);
        }
    
        return $cache->remember($cacheKey, $minutes * 60, $callback);
    }
    
    /**
     * Retrieve the sum of the values of a given column from cache.
     *
     * @param string $column
     * @return mixed
     */
    public function sumFromCache($column)
    {
        $cacheKey = $this->getCacheKey([
            'sum',
            $column
        ]);
    
        $minutes = $this->cacheMinutes ?: config('model-cache.cache_duration', 60);
        $cacheTags = $this->getCacheTags();
        $cache = $this->getCacheDriver();
    
        $callback = function () use ($column) {
            return parent::sum($column);
        };
    
        if ($cacheTags && $this->supportsTags($cache)) {
            return $cache->tags($cacheTags)->remember($cacheKey, $minutes * 60, $callback);
        }
    
        return $cache->remember($cacheKey, $minutes * 60, $callback);
    }
    
    /**
     * Retrieve the maximum value of a given column from cache.
     *
     * @param string $column
     * @return mixed
     */
    public function maxFromCache($column)
    {
        $cacheKey = $this->getCacheKey([
            'max',
            $column
        ]);
    
        $minutes = $this->cacheMinutes ?: config('model-cache.cache_duration', 60);
        $cacheTags = $this->getCacheTags();
        $cache = $this->getCacheDriver();
    
        $callback = function () use ($column) {
            return parent::max($column);
        };
    
        if ($cacheTags && $this->supportsTags($cache)) {
            return $cache->tags($cacheTags)->remember($cacheKey, $minutes * 60, $callback);
        }
    
        return $cache->remember($cacheKey, $minutes * 60, $callback);
    }
    
    /**
     * Retrieve the minimum value of a given column from cache.
     *
     * @param string $column
     * @return mixed
     */
    public function minFromCache($column)
    {
        $cacheKey = $this->getCacheKey([
            'min',
            $column
        ]);
    
        $minutes = $this->cacheMinutes ?: config('model-cache.cache_duration', 60);
        $cacheTags = $this->getCacheTags();
        $cache = $this->getCacheDriver();
    
        $callback = function () use ($column) {
            return parent::min($column);
        };
    
        if ($cacheTags && $this->supportsTags($cache)) {
            return $cache->tags($cacheTags)->remember($cacheKey, $minutes * 60, $callback);
        }
    
        return $cache->remember($cacheKey, $minutes * 60, $callback);
    }
    
    /**
     * Retrieve the average of the values of a given column from cache.
     *
     * @param string $column
     * @return mixed
     */
    public function avgFromCache($column)
    {
        $cacheKey = $this->getCacheKey([
            'avg',
            $column
        ]);
    
        $minutes = $this->cacheMinutes ?: config('model-cache.cache_duration', 60);
        $cacheTags = $this->getCacheTags();
        $cache = $this->getCacheDriver();
    
        $callback = function () use ($column) {
            return parent::avg($column);
        };
    
        if ($cacheTags && $this->supportsTags($cache)) {
            return $cache->tags($cacheTags)->remember($cacheKey, $minutes * 60, $callback);
        }
    
        return $cache->remember($cacheKey, $minutes * 60, $callback);
    }
    
    /**
     * Override the count method to automatically use cache.
     *
     * @param string $columns
     * @return int
     */
    public function count($columns = '*')
    {
        // Only use cache if cacheMinutes is not set to 0
        if (isset($this->cacheMinutes) && $this->cacheMinutes === 0) {
            return parent::count($columns);
        }
    
        return $this->countFromCache($columns);
    }
    
    /**
     * Override the sum method to automatically use cache.
     *
     * @param string $column
     * @return mixed
     */
    public function sum($column)
    {
        // Only use cache if cacheMinutes is not set to 0
        if (isset($this->cacheMinutes) && $this->cacheMinutes === 0) {
            return parent::sum($column);
        }
    
        return $this->sumFromCache($column);
    }
    
    /**
     * Override the max method to automatically use cache.
     *
     * @param string $column
     * @return mixed
     */
    public function max($column)
    {
        // Only use cache if cacheMinutes is not set to 0
        if (isset($this->cacheMinutes) && $this->cacheMinutes === 0) {
            return parent::max($column);
        }
    
        return $this->maxFromCache($column);
    }
    
    /**
     * Override the min method to automatically use cache.
     *
     * @param string $column
     * @return mixed
     */
    public function min($column)
    {
        // Only use cache if cacheMinutes is not set to 0
        if (isset($this->cacheMinutes) && $this->cacheMinutes === 0) {
            return parent::min($column);
        }
    
        return $this->minFromCache($column);
    }
    
    /**
     * Override the avg method to automatically use cache.
     *
     * @param string $column
     * @return mixed
     */
    public function avg($column)
    {
        // Only use cache if cacheMinutes is not set to 0
        if (isset($this->cacheMinutes) && $this->cacheMinutes === 0) {
            return parent::avg($column);
        }
    
        return $this->avgFromCache($column);
    }
    
    /**
     * Alias for the "avg" method.
     *
     * @param string $column
     * @return mixed
     */
    public function average($column)
    {
        return $this->avg($column);
    }

    /**
     * Update records in the database and flush cache.
     *
     * @param array $values
     * @return int
     */
    public function update(array $values)
    {
        // Execute the update operation
        $result = parent::update($values);

        // Flush the cache for this model
        if ($result) {
            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Flushing cache after mass update for model: " . get_class($this->model));
            }

            // Try to flush the model cache
            if (method_exists($this->model, 'flushModelCache')) {
                $this->model->flushModelCache();
            } else {
                // Fallback to flushing the query cache
                $this->flushQueryCache();
            }
        }

        return $result;
    }

    /**
     * Delete records from the database and flush cache.
     *
     * @return mixed
     */
    public function delete()
    {
        // Execute the delete operation
        $result = parent::delete();

        // Flush the cache for this model if any records were deleted
        if ($result) {
            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Flushing cache after mass delete for model: " . get_class($this->model));
            }

            // Try to flush the model cache
            if (method_exists($this->model, 'flushModelCache')) {
                $this->model->flushModelCache();
            } else {
                // Fallback to flushing the query cache
                $this->flushQueryCache();
            }
        }

        return $result;
    }

    /**
     * Increment a column's value by a given amount and flush cache.
     *
     * @param string $column
     * @param float|int $amount
     * @param array $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        // Execute the increment operation
        $result = parent::increment($column, $amount, $extra);

        // Flush the cache for this model
        if ($result) {
            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Flushing cache after increment operation for model: " . get_class($this->model));
            }

            // Try to flush the model cache
            if (method_exists($this->model, 'flushModelCache')) {
                $this->model->flushModelCache();
            } else {
                // Fallback to flushing the query cache
                $this->flushQueryCache();
            }
        }

        return $result;
    }

    /**
     * Decrement a column's value by a given amount and flush cache.
     *
     * @param string $column
     * @param float|int $amount
     * @param array $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        // Execute the decrement operation
        $result = parent::decrement($column, $amount, $extra);

        // Flush the cache for this model
        if ($result) {
            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Flushing cache after decrement operation for model: " . get_class($this->model));
            }

            // Try to flush the model cache
            if (method_exists($this->model, 'flushModelCache')) {
                $this->model->flushModelCache();
            } else {
                // Fallback to flushing the query cache
                $this->flushQueryCache();
            }
        }

        return $result;
    }

    /**
     * Insert new records into the database and flush cache.
     *
     * @param array $values
     * @return bool
     */
    public function insert(array $values)
    {
        // Execute the insert operation
        $result = parent::insert($values);

        // Flush the cache for this model if insert was successful
        if ($result) {
            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Flushing cache after insert operation for model: " . get_class($this->model));
            }

            // Try to flush the model cache
            if (method_exists($this->model, 'flushModelCache')) {
                $this->model->flushModelCache();
            } else {
                // Fallback to flushing the query cache
                $this->flushQueryCache();
            }
        }

        return $result;
    }

    /**
     * Insert new records into the database while ignoring errors and flush cache.
     *
     * @param array $values
     * @return int
     */
    public function insertOrIgnore(array $values)
    {
        // Execute the insertOrIgnore operation
        $result = parent::insertOrIgnore($values);

        // Flush the cache for this model if any records were inserted
        if ($result > 0) {
            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Flushing cache after insertOrIgnore operation for model: " . get_class($this->model));
            }

            // Try to flush the model cache
            if (method_exists($this->model, 'flushModelCache')) {
                $this->model->flushModelCache();
            } else {
                // Fallback to flushing the query cache
                $this->flushQueryCache();
            }
        }

        return $result;
    }

    /**
     * Insert a new record and get the value of the primary key and flush cache.
     *
     * @param array $values
     * @param string|null $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        // Execute the insertGetId operation
        $result = parent::insertGetId($values, $sequence);

        // Flush the cache for this model
        if ($result) {
            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Flushing cache after insertGetId operation for model: " . get_class($this->model));
            }

            // Try to flush the model cache
            if (method_exists($this->model, 'flushModelCache')) {
                $this->model->flushModelCache();
            } else {
                // Fallback to flushing the query cache
                $this->flushQueryCache();
            }
        }

        return $result;
    }

    /**
     * Insert or update a record matching the attributes, and fill it with values.
     *
     * @param array $attributes
     * @param array $values
     * @return bool
     */
    public function updateOrInsert(array $attributes, $values = [])
    {
        // Execute the updateOrInsert operation
        $result = parent::updateOrInsert($attributes, $values);

        // Flush the cache for this model if operation was successful
        if ($result) {
            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Flushing cache after updateOrInsert operation for model: " . get_class($this->model));
            }

            // Try to flush the model cache
            if (method_exists($this->model, 'flushModelCache')) {
                $this->model->flushModelCache();
            } else {
                // Fallback to flushing the query cache
                $this->flushQueryCache();
            }
        }

        return $result;
    }

    /**
     * Insert new records or update the existing ones and flush cache.
     *
     * @param array $values
     * @param array|string $uniqueBy
     * @param array|null $update
     * @return int
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        // Check if upsert method exists in the parent (Laravel 8+)
        if (!method_exists(get_parent_class($this), 'upsert')) {
            throw new \BadMethodCallException('Method upsert() is not supported by the database driver.');
        }

        // Execute the upsert operation
        $result = parent::upsert($values, $uniqueBy, $update);

        // Flush the cache for this model if any records were affected
        if ($result > 0) {
            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Flushing cache after upsert operation for model: " . get_class($this->model));
            }

            // Try to flush the model cache
            if (method_exists($this->model, 'flushModelCache')) {
                $this->model->flushModelCache();
            } else {
                // Fallback to flushing the query cache
                $this->flushQueryCache();
            }
        }

        return $result;
    }

    /**
     * Truncate the table and flush cache.
     *
     * @return void
     */
    public function truncate()
    {
        // Execute the truncate operation
        parent::truncate();

        // Always flush the cache after truncate
        if (config('model-cache.debug_mode', false) && function_exists('logger')) {
            logger()->info("Flushing cache after truncate operation for model: " . get_class($this->model));
        }

        // Try to flush the model cache
        if (method_exists($this->model, 'flushModelCache')) {
            $this->model->flushModelCache();
        } else {
            // Fallback to flushing the query cache
            $this->flushQueryCache();
        }
    }

    /**
     * Force a hard delete on a soft deleted model and flush cache.
     * This method overrides the forceDelete method present in the SoftDeletes trait.
     *
     * @return mixed
     */
    public function forceDelete()
    {
        // Check if the model uses SoftDeletes
        if (!method_exists($this->model, 'runSoftDelete')) {
            return $this->delete();
        }

        // Execute the force delete operation
        $result = parent::forceDelete();

        // Flush the cache for this model
        if ($result) {
            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Flushing cache after force delete for model: " . get_class($this->model));
            }

            // Try to flush the model cache
            if (method_exists($this->model, 'flushModelCache')) {
                $this->model->flushModelCache();
            } else {
                // Fallback to flushing the query cache
                $this->flushQueryCache();
            }
        }

        return $result;
    }

    /**
     * Restore soft deleted models and flush cache.
     * This method overrides the restore method present in the SoftDeletes trait.
     *
     * @return mixed
     */
    public function restore()
    {
        // Check if the model uses SoftDeletes
        if (!method_exists($this->model, 'runSoftDelete')) {
            return 0;
        }

        // Execute the restore operation
        $result = parent::restore();

        // Flush the cache for this model
        if ($result) {
            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Flushing cache after restore for model: " . get_class($this->model));
            }

            // Try to flush the model cache
            if (method_exists($this->model, 'flushModelCache')) {
                $this->model->flushModelCache();
            } else {
                // Fallback to flushing the query cache
                $this->flushQueryCache();
            }
        }

        return $result;
    }
}
