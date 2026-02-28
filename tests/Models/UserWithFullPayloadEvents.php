<?php

namespace GeneaLabs\LaravelPivotEvents\Tests\Models;

use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotAttached;
use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotAttaching;
use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotDetached;
use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotSynced;
use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotUpdated;
use GeneaLabs\LaravelPivotEvents\Traits\PivotEventTrait;

class UserWithFullPayloadEvents extends BaseModel
{
    use PivotEventTrait;

    protected $table = 'users';
    protected $fillable = ['name'];

    protected $dispatchesEvents = [
        'pivotAttaching' => PayloadAwarePivotAttaching::class,
        'pivotAttached'  => PayloadAwarePivotAttached::class,
        'pivotDetached'  => PayloadAwarePivotDetached::class,
        'pivotSynced'    => PayloadAwarePivotSynced::class,
        'pivotUpdated'   => PayloadAwarePivotUpdated::class,
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withPivot(['value']);
    }
}
