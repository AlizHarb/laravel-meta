<?php

namespace AlizHarb\Meta\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use AlizHarb\Meta\Models\Meta;

class MetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_meta(): void
    {
        $meta = Meta::create([
            'key' => 'test',
            'type' => 'string',
            'value_string' => 'value'
        ]);

        $this->assertDatabaseHas('metas', ['key' => 'test']);
        $this->assertEquals('value', $meta->getRealValue());
    }
}
