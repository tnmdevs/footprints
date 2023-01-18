<?php

namespace TNM\Footprints\Tests\Unit;

use TNM\Footprints\Models\Footprint;
use TNM\Footprints\Tests\TestCase;

class FootprintsTest extends TestCase
{
    public function test_create_footprint()
    {
        Footprint::factory()->create();
        $this->assertDatabaseCount(Footprint::class, 1);
    }
}