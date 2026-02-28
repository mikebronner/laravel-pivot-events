<?php

namespace GeneaLabs\LaravelPivotEvents\Tests\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Legacy custom event for pivotDetached — only accepts the model.
 */
class LegacyPivotDetached
{
    public Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }
}
