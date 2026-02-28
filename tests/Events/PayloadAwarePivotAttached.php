<?php

namespace GeneaLabs\LaravelPivotEvents\Tests\Events;

use GeneaLabs\LaravelPivotEvents\Contracts\ReceivesPivotPayload;
use Illuminate\Database\Eloquent\Model;

/**
 * Custom event class that opts in to receiving the full pivot payload.
 */
class PayloadAwarePivotAttached implements ReceivesPivotPayload
{
    public Model $model;
    public array $payload;

    public function __construct(Model $model, array $payload)
    {
        $this->model   = $model;
        $this->payload = $payload;
    }
}
