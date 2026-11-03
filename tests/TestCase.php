<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\BlaspServiceProvider;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{

    protected function getPackageProviders($app)
    {
        return [
            BlaspServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // In-memory SQLite for the model tests; older Testbench skeletons default to MySQL
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('cache.default', 'array');
        Config::set('blasp.separators', config('blasp.separators'));
        Config::set('blasp.profanities', config('blasp.profanities'));
        Config::set('blasp.false_positives', config('blasp.false_positives', []));
        Config::set('blasp.substitutions', config('blasp.substitutions', []));
        Config::set('blasp.mask', '*');
        Config::set('blasp.mask_character', '*');
        Config::set('blasp.cache.driver', config('blasp.cache.driver'));
        Config::set('blasp.cache_driver', config('blasp.cache_driver'));
    }
}
