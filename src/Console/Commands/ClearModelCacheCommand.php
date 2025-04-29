<?php

namespace YMigVal\LaravelModelCache\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

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

        return Command::SUCCESS;
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

        try {
            $model = new $modelClass();
            $tableName = $model->getTable();

            if (method_exists($model, 'flushCache')) {
                $model->flushCache();
                $this->info("Cache cleared for model: {$modelClass}");
            } elseif (method_exists($model, 'flushModelCache')) {
                // For backward compatibility
                $model->flushModelCache();
                $this->info("Cache cleared for model: {$modelClass}");
            } else {
                $this->clearModelCacheManually($modelClass, $tableName);
            }
        } catch (\Exception $e) {
            $this->error("Error clearing cache for {$modelClass}: " . $e->getMessage());
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
        $cacheStore = config('model-cache.cache_store');
        $cache = $cacheStore ? Cache::store($cacheStore) : Cache::store();
        
        $tags = ['model_cache', $modelClass, $tableName];
        
        if (method_exists($cache, 'tags')) {
            $cache->tags($tags)->flush();
            $this->info("Cache cleared for model: {$modelClass} using tags");
        } else {
            // For cache drivers that don't support tags
            $this->warn("Your cache driver doesn't support tags. Using basic cache clear...");
            $prefix = config('model-cache.cache_key_prefix', 'model_cache_');
            
            // Clear all cache as we can't target specific model without tags
            $cache->flush();
            $this->info("All cache cleared (cannot target specific model without tags support)");
        }
    }

    /**
     * Clear cache for all models.
     *
     * @return void
     */
    protected function clearAllModelCache()
    {
        $cacheStore = config('model-cache.cache_store');
        $cache = $cacheStore ? Cache::store($cacheStore) : Cache::store();
        
        if (method_exists($cache, 'tags')) {
            $cache->tags('model_cache')->flush();
            $this->info("Cache cleared for all models");
        } else {
            // For cache drivers that don't support tags
            $this->warn("Your cache driver doesn't support tags. Using full cache clear...");
            $cache->flush();
            $this->info("All cache cleared");
        }
    }
}
