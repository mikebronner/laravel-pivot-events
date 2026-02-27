<?php

namespace GeneaLabs\LaravelPivotEvents\Tests\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Simulates an existing custom event class that only accepts the model.
 * Must continue to work without ArgumentCountError after the fix.
 */
class LegacyPivotAttached
{
    public Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }
}
