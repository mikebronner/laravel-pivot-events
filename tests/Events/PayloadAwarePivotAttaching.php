<?php

namespace GeneaLabs\LaravelPivotEvents\Tests\Events;

use GeneaLabs\LaravelPivotEvents\Contracts\ReceivesPivotPayload;
use Illuminate\Database\Eloquent\Model;

/**
 * Payload-aware custom event for pivotAttaching (pre-operation).
 */
class PayloadAwarePivotAttaching implements ReceivesPivotPayload
{
    public Model $model;
    public array $payload;

    public function __construct(Model $model, array $payload)
    {
        $this->model   = $model;
        $this->payload = $payload;
    }
}
