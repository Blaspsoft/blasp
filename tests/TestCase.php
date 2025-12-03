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
        // Set up basic configuration - language-specific data will be loaded by ConfigurationLoader
        Config::set('blasp.separators', config('blasp.separators'));
        Config::set('blasp.profanities', config('blasp.profanities')); // Minimal set for backward compatibility
        Config::set('blasp.false_positives', config('blasp.false_positives', []));
        Config::set('blasp.languages', config('blasp.languages', []));
        Config::set('blasp.substitutions', config('blasp.substitutions', []));
        Config::set('blasp.mask_character', '*'); // Default mask character
        Config::set('blasp.cache_driver', config('blasp.cache_driver'));
    }
}
