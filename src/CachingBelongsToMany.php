<?php

namespace YMigVal\LaravelModelCache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CachingBelongsToMany extends BelongsToMany
{
    /**
     * The parent model that should have its cache flushed.
     *
     * @var Model
     */
    protected $cacheableParent;

    /**
     * Create a new belongs to many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string  $relationName
     * @param  Model  $cacheableParent
     * @return void
     */
    public function __construct($query, $parent, $table, $foreignPivotKey, 
                               $relatedPivotKey, $parentKey, $relatedKey, 
                               $relationName = null, $cacheableParent = null)
    {
        parent::__construct(
            $query, $parent, $table, $foreignPivotKey, 
            $relatedPivotKey, $parentKey, $relatedKey, $relationName
        );
        
        // Store the parent model that has the cache trait
        $this->cacheableParent = $cacheableParent ?: $parent;
    }

    /**
     * Attach a model to the parent.
     *
     * @param  mixed  $id
     * @param  array  $attributes
     * @param  bool  $touch
     * @return void
     */
    public function attach($id, array $attributes = [], $touch = true)
    {
        // Call parent method to perform the actual attach
        parent::attach($id, $attributes, $touch);
        
        // Flush cache after operation
        $this->flushCache('attach');
    }

    /**
     * Detach models from the relationship.
     *
     * @param  mixed  $ids
     * @param  bool  $touch
     * @return int
     */
    public function detach($ids = null, $touch = true)
    {
        // Call parent method to perform the actual detach
        $result = parent::detach($ids, $touch);
        
        // Flush cache after operation
        $this->flushCache('detach');
        
        return $result;
    }

    /**
     * Sync the intermediate tables with a list of IDs or collection of models.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model|array  $ids
     * @param  bool  $detaching
     * @return array
     */
    public function sync($ids, $detaching = true)
    {
        // Call parent method to perform the actual sync
        $result = parent::sync($ids, $detaching);
        
        // Flush cache after operation
        $this->flushCache('sync');
        
        return $result;
    }

    /**
     * Update an existing pivot record on the table.
     *
     * @param  mixed  $id
     * @param  array  $attributes
     * @param  bool  $touch
     * @return int
     */
    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        // Call parent method to perform the actual update
        $result = parent::updateExistingPivot($id, $attributes, $touch);
        
        // Flush cache after operation
        $this->flushCache('updateExistingPivot');
        
        return $result;
    }

    /**
     * Sync the intermediate tables with a list of IDs without detaching.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model|array  $ids
     * @return array
     */
    public function syncWithoutDetaching($ids)
    {
        // Call parent method to perform the actual sync
        $result = parent::syncWithoutDetaching($ids);
        
        // Flush cache after operation
        $this->flushCache('syncWithoutDetaching');
        
        return $result;
    }

    /**
     * Flush the model cache after a relationship operation.
     *
     * @param  string  $operation
     * @return void
     */
    protected function flushCache($operation)
    {
        if (method_exists($this->cacheableParent, 'flushModelCache')) {
            $this->cacheableParent->flushModelCache();
        } else {
            if (method_exists($this->cacheableParent, 'flushCache')) {
                $this->cacheableParent->flushCache();
            } else {
                throw new \Exception('The parent model must have a flushCache() or flushModelCache() method defined. Make sure your model uses the HasCachedQueries trait. The ModelRelationships trait should be used in conjunction with the HasCachedQueries trait. See the documentation for more information.');
            }
        }

        if (config('model-cache.debug_mode', false) && function_exists('logger')) {
            logger()->info("Cache flushed after {$operation} operation for model: " . get_class($this->cacheableParent));
        }
    }
}
