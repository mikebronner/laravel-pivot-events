<?php

namespace GeneaLabs\LaravelPivotEvents\Tests\Models;

use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotAttached;
use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotDetached;
use GeneaLabs\LaravelPivotEvents\Traits\PivotEventTrait;

class VideoWithPayloadEvent extends BaseModel
{
    use PivotEventTrait;

    protected $table = 'videos';
    protected $fillable = ['name'];

    protected $dispatchesEvents = [
        'pivotAttached' => PayloadAwarePivotAttached::class,
        'pivotDetached' => PayloadAwarePivotDetached::class,
    ];

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable', 'taggables')
            ->withPivot(['value']);
    }
}
