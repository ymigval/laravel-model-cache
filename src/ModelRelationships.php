<?php

namespace YMigVal\LaravelModelCache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Helper trait to flush cache when relationship methods are called.
 *
 * This trait can be used alongside HasCachedQueries to ensure that
 * operations on model relationships also flush the cache appropriately.
 * @method newRelatedInstance(string $related)
 * @method getForeignKey()
 * @method joiningTable(string $related)
 * @method getKeyName()
 */
trait ModelRelationships
{
    /**
     * Override the belongsToMany relationship method to return a custom
     * relationship class that handles cache flushing after operations.
     *
     * @param  string  $related
     * @param  string|null  $table
     * @param  string|null  $foreignPivotKey
     * @param  string|null  $relatedPivotKey
     * @param  string|null  $parentKey
     * @param  string|null  $relatedKey
     * @param  string|null  $relation
     * @return \YMigVal\LaravelModelCache\CachingBelongsToMany
     */
    public function belongsToMany($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null,
                                  $parentKey = null, $relatedKey = null, $relation = null)
    {
        // Get the original relationship instance
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();
        
        // Determine the relationship name if not provided
        if (is_null($relation)) {
            $relation = $this->guessBelongsToManyRelation();
        }

        // Generate table name if not provided
        if (is_null($table)) {
            $table = $this->joiningTable($related);
        }

        // Create our caching BelongsToMany relationship
        return new CachingBelongsToMany(
            $instance->newQuery(), 
            $this, 
            $table,
            $foreignPivotKey, 
            $relatedPivotKey, 
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(), 
            $relation, 
            $this
        );
    }

    /**
     * Override the belongsToMany relation's sync method to flush cache.
     *
     * @param string $relation
     * @param array $ids
     * @param bool $detaching
     * @return array
     */
    public function syncRelationshipAndFlushCache($relation, array $ids, $detaching = true)
    {
        if (!method_exists($this, $relation)) {
            throw new \BadMethodCallException("Method {$relation} does not exist.");
        }

        $result = $this->$relation()->sync($ids, $detaching);

        // Flush the cache
        if (method_exists($this, 'flushModelCache')) {
            $this->flushModelCache();
        } else {
            if (method_exists($this->cacheableParent, 'flushCache')) {
                $this->cacheableParent->flushCache();
            } else {
                throw new \Exception('The parent model must have a flushCache() or flushModelCache() method defined. Make sure your model uses the HasCachedQueries trait. The ModelRelationships trait should be used in conjunction with the HasCachedQueries trait. See the documentation for more information.');
            }
        }

        if (config('model-cache.debug_mode', false) && function_exists('logger')) {
            logger()->info("Cache flushed after detach operation for model: " . get_class($this));
        }

        return $result;
    }

    /**
     * Override the belongsToMany relation's attach method to flush cache.
     *
     * @param string $relation
     * @param mixed $ids
     * @param array $attributes
     * @param bool $touch
     * @return void
     */
    public function attachRelationshipAndFlushCache($relation, $ids, array $attributes = [], $touch = true)
    {
        if (!method_exists($this, $relation)) {
            throw new \BadMethodCallException("Method {$relation} does not exist.");
        }

        $this->$relation()->attach($ids, $attributes, $touch);

        // Flush the cache
        if (method_exists($this, 'flushModelCache')) {
            $this->flushModelCache();
        } else {
            if (method_exists($this->cacheableParent, 'flushCache')) {
                $this->cacheableParent->flushCache();
            } else {
                throw new \Exception('The parent model must have a flushCache() or flushModelCache() method defined. Make sure your model uses the HasCachedQueries trait. The ModelRelationships trait should be used in conjunction with the HasCachedQueries trait. See the documentation for more information.');
            }
        }

        if (config('model-cache.debug_mode', false) && function_exists('logger')) {
            logger()->info("Cache flushed after detach operation for model: " . get_class($this));
        }
    }

    /**
     * Override the belongsToMany relation's detach method to flush cache.
     *
     * @param string $relation
     * @param mixed $ids
     * @param bool $touch
     * @return int
     */
    public function detachRelationshipAndFlushCache($relation, $ids = null, $touch = true)
    {
        if (!method_exists($this, $relation)) {
            throw new \BadMethodCallException("Method {$relation} does not exist.");
        }

        $result = $this->$relation()->detach($ids, $touch);

        // Flush the cache
        if (method_exists($this, 'flushModelCache')) {
            $this->flushModelCache();
        } else {
            if (method_exists($this->cacheableParent, 'flushCache')) {
                $this->cacheableParent->flushCache();
            } else {
                throw new \Exception('The parent model must have a flushCache() or flushModelCache() method defined. Make sure your model uses the HasCachedQueries trait. The ModelRelationships trait should be used in conjunction with the HasCachedQueries trait. See the documentation for more information.');
            }
        }

        if (config('model-cache.debug_mode', false) && function_exists('logger')) {
            logger()->info("Cache flushed after detach operation for model: " . get_class($this));
        }

        return $result;
    }

    /**
     * Get the relationship name from the backtrace.
     *
     * @return string
     */
    protected function guessBelongsToManyRelation()
    {
        list($one, $two, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return $caller['function'];
    }
}
