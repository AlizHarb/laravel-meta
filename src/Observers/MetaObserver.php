<?php

declare(strict_types=1);

namespace AlizHarb\Meta\Observers;

use Illuminate\Database\Eloquent\Model;

class MetaObserver
{
    /**
     * Handle the "saved" event.
     *
     * @param Model $model
     */
    public function saved(Model $model): void
    {
        if (method_exists($model, 'persistQueuedModel')) {
            $model->persistQueuedModel();
        }
    }

    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        if (method_exists($model, 'persistQueuedModel')) {
            $model->persistQueuedModel();
        }
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        if (method_exists($model, 'persistQueuedModel')) {
            $model->persistQueuedModel();
        }
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        if (method_exists($model, 'Models')) {
            $model->Models()->delete();
        }
    }

    /**
     * Handle the Model "restored" event.
     */
    public function restored(Model $model): void
    {
        //
    }

    /**
     * Handle the Model "force deleted" event.
     */
    public function forceDeleted(Model $model): void
    {
        //
    }
}
