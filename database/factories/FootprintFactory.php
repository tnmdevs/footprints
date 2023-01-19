<?php

namespace TNM\Footprints\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use TNM\Footprints\Models\Footprint;

class FootprintFactory extends Factory
{
    protected $model = Footprint::class;

    public function definition()
    {
        $startTime = microtime(true) * 1000;
        return [
            'endpoint' => fake()->slug(),
            'uri' => fake()->slug(),
            'method' => fake()->randomElement(['post', 'get']),
            'milliseconds' => microtime(true) * 1000 - $startTime,
            'status' => 200,
            'success' => true
        ];
    }

}
