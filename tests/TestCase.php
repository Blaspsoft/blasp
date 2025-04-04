<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('blasp.profanities', config('blasp.profanities'));
        Config::set('blasp.false_positives', config('blasp.false_positives'));
        Config::set('blasp.languages', config('blasp.languages'));
        Config::set('blasp.separators', config('blasp.separators'));
        Config::set('blasp.substitutions', config('blasp.substitutions'));
        Config::set('blasp.cache_driver', config('blasp.cache_driver'));
    }
}
