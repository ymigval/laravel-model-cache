<?php

namespace YMigVal\LaravelModelCache;

use Illuminate\Database\Eloquent\Model;

/**
 * Helper trait to flush cache when relationship methods are called.
 *
 * This trait can be used alongside HasCachedQueries to ensure that
 * operations on model relationships also flush the cache appropriately.
 */
trait ModelRelationships
{
    /**
     * Initialize the trait.
     *
     * @return void
     */
    public function initializeModelRelationships()
    {
        // Register a callback to execute after Eloquent relationship methods
        $this->registerModelEvent('belongsToMany.saved', function ($relation, $parent, $ids, $attributes) {
            $this->flushRelationshipCache($parent);
        });

        $this->registerModelEvent('belongsToMany.attached', function ($relation, $parent, $ids, $attributes) {
            $this->flushRelationshipCache($parent);
        });

        $this->registerModelEvent('belongsToMany.detached', function ($relation, $parent, $ids, $attributes) {
            $this->flushRelationshipCache($parent);
        });

        $this->registerModelEvent('belongsToMany.synced', function ($relation, $parent, $ids, $attributes) {
            $this->flushRelationshipCache($parent);
        });

        $this->registerModelEvent('belongsToMany.updated', function ($relation, $parent, $ids, $attributes) {
            $this->flushRelationshipCache($parent);
        });
    }

    /**
     * Flush cache after a relationship operation.
     *
     * @param Model $model
     * @return void
     */
    protected function flushRelationshipCache(Model $model)
    {
        if (method_exists($model, 'flushModelCache')) {
            $model->flushModelCache();

            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Cache flushed after relationship operation for model: " . get_class($model));
            }
        }
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

            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Cache flushed after sync operation for model: " . get_class($this));
            }
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

            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Cache flushed after attach operation for model: " . get_class($this));
            }
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

            if (config('model-cache.debug_mode', false) && function_exists('logger')) {
                logger()->info("Cache flushed after detach operation for model: " . get_class($this));
            }
        }

        return $result;
    }
}
