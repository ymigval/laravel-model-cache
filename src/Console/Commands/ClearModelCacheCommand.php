<?php

namespace YMigVal\LaravelModelCache\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ClearModelCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcache:flush {model? : The model class name (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear cache for specific model or all cached models';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $modelClass = $this->argument('model');

        if ($modelClass) {
            $this->clearModelCache($modelClass);
        } else {
            $this->clearAllModelCache();
        }

        return CommandAlias::SUCCESS;
    }

    /**
     * Clear cache for a specific model.
     *
     * @param string $modelClass
     * @return void
     */
    protected function clearModelCache(string $modelClass)
    {
        if (!class_exists($modelClass)) {
            $this->error("Model class {$modelClass} does not exist!");
            return;
        }

        $this->info("Attempting to clear cache for model: {$modelClass}");

        // Check if the model uses our trait
        if (!$this->usesHasCachedQueriesTrait($modelClass)) {
            $this->warn("Warning: The model {$modelClass} doesn't use HasCachedQueries trait. Cache functionality might be limited.");
        }

        // Show the current cache configuration
        $this->info("Current cache driver: " . config('cache.default'));
        $this->info("Model cache store: " . config('model-cache.cache_store', 'default'));
    
        try {
            // First check if the model has static flush methods
            if (method_exists($modelClass, 'flushCacheStatic')) {
                $this->info("Found static method: flushCacheStatic");
                $result = $modelClass::flushCacheStatic();
                if ($result) {
                    $this->info("Cache cleared successfully for model: {$modelClass} using static method");
                } else {
                    $this->warn("Static method returned false - cache may not have been cleared completely");
                    // Force a full cache clear as a backup
                    $this->performFullCacheFlush();
                }
                return;
            } elseif (method_exists($modelClass, 'flushModelCache') &&
                is_callable([$modelClass, 'flushModelCache']) &&
                (new \ReflectionMethod($modelClass, 'flushModelCache'))->isStatic()) {
                // For backward compatibility - check if static flushModelCache exists
                $this->info("Found static method: flushModelCache");
                $result = $modelClass::flushModelCache();
                if ($result) {
                    $this->info("Cache cleared successfully for model: {$modelClass} using static method");
                } else {
                    $this->warn("Static method returned false - cache may not have been cleared completely");
                    // Force a full cache clear as a backup
                    $this->performFullCacheFlush();
                }
                return;
            }

            $this->info("No static methods found. Trying with instance methods...");

            // If no static methods, try instance methods
            $model = new $modelClass();
            $tableName = $model->getTable();
            $this->info("Model table: {$tableName}");
    
            if (method_exists($model, 'flushCache')) {
                $this->info("Found instance method: flushCache");
                $result = $model->flushCache();
                if ($result) {
                    $this->info("Cache cleared successfully for model: {$modelClass}");
                } else {
                    $this->warn("Instance method returned false - cache may not have been cleared completely");
                    // Force a full cache clear as a backup
                    $this->performFullCacheFlush();
                }
            } elseif (method_exists($model, 'flushModelCache')) {
                // For backward compatibility
                $this->info("Found instance method: flushModelCache");
                $result = $model->flushModelCache();
                if ($result) {
                    $this->info("Cache cleared successfully for model: {$modelClass}");
                } else {
                    $this->warn("Instance method returned false - cache may not have been cleared completely");
                    // Force a full cache clear as a backup
                    $this->performFullCacheFlush();
                }
            } else {
                $this->warn("No cache flush methods found on the model. Using manual clearing...");
                $this->clearModelCacheManually($modelClass, $tableName);
            }
        } catch (\Exception $e) {
            $this->error("Error clearing cache for {$modelClass}: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());

            // Ask if user wants to try full cache flush as a last resort
            if ($this->confirm('Would you like to clear the entire application cache?', true)) {
                $this->performFullCacheFlush();
            }
        }
    }

    /**
     * Check if a model uses the HasCachedQueries trait.
     *
     * @param string $class
     * @return bool
     */
    protected function usesHasCachedQueriesTrait($class)
    {
        $traits = class_uses_recursive($class);
        return isset($traits['YMigVal\LaravelModelCache\HasCachedQueries']);
    }

    /**
     * Perform a full cache flush as a last resort.
     *
     * @return void
     */
    protected function performFullCacheFlush()
    {
        $this->info("Performing full cache flush as a fallback...");

        try {
            // Get the cache driver
            $cacheStore = config('model-cache.cache_store');
            $cache = $cacheStore ? \Illuminate\Support\Facades\Cache::store($cacheStore) : \Illuminate\Support\Facades\Cache::store();

            // Flush everything
            $cache->flush();
            $this->info("Full application cache has been cleared successfully");
        } catch (\Exception $e) {
            $this->error("Error performing full cache flush: " . $e->getMessage());
        }
    }

    /**
     * Clear cache manually when flushModelCache is not available.
     *
     * @param string $modelClass
     * @param string $tableName
     * @return void
     */
    protected function clearModelCacheManually(string $modelClass, string $tableName)
    {
        try {
            // Try to get the configured cache store
            $cacheStore = config('model-cache.cache_store');
            $cache = $cacheStore ? Cache::store($cacheStore) : Cache::store();

            $tags = ['model_cache', $modelClass, $tableName];

            // First try to use tags if supported
            if ($this->supportsTags($cache)) {
                try {
                    $cache->tags($tags)->flush();
                    $this->info("Cache cleared for model: {$modelClass} using tags");
                    return;
                } catch (\Exception $e) {
                    $this->warn("Error using cache tags: " . $e->getMessage());
                }
            }

            // If we reach here, tags are not supported or failed
            // For simplicity, just confirm and clear all cache
            if ($this->confirm("Your cache driver doesn't support tags or there was an error. Would you like to clear ALL application cache?", false)) {
                $cache->flush();
                $this->info("All cache cleared successfully");
            } else {
                $this->info("Cache clearing cancelled");
            }

        } catch (\Exception $e) {
            $this->error("Error clearing cache: " . $e->getMessage());
        }
    }

    /**
     * Clear cache for all models.
     *
     * @return void
     */
    protected function clearAllModelCache()
    {
        try {
            // Try to get the configured cache store
            $cacheStore = config('model-cache.cache_store');
            $cache = $cacheStore ? Cache::store($cacheStore) : Cache::store();

            // First try to use tags if supported
            if ($this->supportsTags($cache)) {
                try {
                    $cache->tags('model_cache')->flush();
                    $this->info("Cache cleared for all models using tags");
                    return;
                } catch (\Exception $e) {
                    $this->warn("Error using cache tags: " . $e->getMessage());
                }
            }

            // If we reach here, tags are not supported or failed
            // Ask for confirmation before clearing all cache
            if ($this->confirm('Your cache driver doesn\'t support tags. This will clear ALL application cache. Continue?', false)) {
                $cache->flush();
                $this->info("All cache cleared successfully");
            } else {
                $this->info("Cache clearing cancelled");
            }

        } catch (\Exception $e) {
            $this->error("Error clearing cache: " . $e->getMessage());
        }
    }

    /**
     * Check if the cache repository supports tagging.
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
     * Performs a full cache clear when tags aren't supported.
     *
     * @param \Illuminate\Contracts\Cache\Repository $cache
     * @return void
     */
    protected function performFullCacheClear($cache)
    {
        $this->warn("Your cache driver doesn't support tags. Using full cache clear...");

        // Optionally, you can ask for confirmation before clearing all cache
        if ($this->confirm('This will clear ALL application cache, not just model cache. Continue?', true)) {
            $cache->flush();
            $this->info("All cache cleared (cannot target specific model without tags support)");
        } else {
            $this->info("Cache clearing cancelled");
        }
    }
}
