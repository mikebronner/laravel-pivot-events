<?php

namespace GeneaLabs\LaravelPivotEvents\Tests\Models;

use GeneaLabs\LaravelPivotEvents\Traits\PivotEventTrait;

class Post extends BaseModel
{
    use PivotEventTrait;

    protected $table = 'posts';

    protected $fillable = ['name'];

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
