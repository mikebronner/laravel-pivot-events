<?php

namespace GeneaLabs\LaravelPivotEvents\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ReceivesPivotPayload
{
    public function __construct(Model $model, array $payload);
}
