<?php

namespace GeneaLabs\LaravelPivotEvents\Tests\Models;

use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotAttached;
use GeneaLabs\LaravelPivotEvents\Traits\PivotEventTrait;

class UserWithPayloadEvent extends BaseModel
{
    use PivotEventTrait;

    protected $table = 'users';
    protected $fillable = ['name'];

    protected $dispatchesEvents = [
        'pivotAttached' => PayloadAwarePivotAttached::class,
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withPivot(['value']);
    }
}
