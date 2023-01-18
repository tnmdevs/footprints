<?php

namespace TNM\Footprints\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use TNM\Footprints\Database\Factories\FootprintFactory;

class Footprint extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory()
    {
        return FootprintFactory::new();
    }
}