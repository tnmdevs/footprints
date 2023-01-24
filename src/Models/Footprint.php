<?php

namespace TNM\Footprints\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use TNM\Footprints\Database\Factories\FootprintFactory;

class Footprint extends Model
{
    use HasFactory, Prunable;

    protected $guarded = [];

    protected static function newFactory()
    {
        return FootprintFactory::new();
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDays(config('footprints.retention_days')));
    }
}