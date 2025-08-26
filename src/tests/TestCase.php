<?php

namespace AlizHarb\Meta\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use AlizHarb\Meta\MetaServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [MetaServiceProvider::class];
    }
}
