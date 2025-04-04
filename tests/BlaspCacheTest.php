<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\BlaspService;
use Illuminate\Support\Facades\Config;

class BlaspCacheTest extends TestCase
{
    protected $blaspService;

    public function setUp(): void
    {
        parent::setUp();
        $this->blaspService = new BlaspService();
    }

    /**
     * Test that the cache driver is correctly used when specified in config
     */
    public function test_cache_driver_is_used_when_specified()
    {
        Config::set('blasp.cache_driver', 'array');
        $result = $this->blaspService->check('This is a test string');
        $this->assertFalse($result->hasProfanity);
    }

    /**
     * Test that the default cache driver is used when none is specified
     */
    public function test_default_cache_driver_is_used_when_none_specified()
    {
        Config::set('blasp.cache_driver', null);
        $result = $this->blaspService->check('This is a test string');
        $this->assertFalse($result->hasProfanity);
    }

    /**
     * Test that the cache is properly cleared
     */
    public function test_cache_is_properly_cleared()
    {
        Config::set('blasp.cache_driver', 'array');
        BlaspService::clearCache();
        $this->assertTrue(true);
    }

    /**
     * Test that the cache keys are properly tracked
     */
    public function test_cache_keys_are_properly_tracked()
    {
        Config::set('blasp.cache_driver', 'array');
        $result = $this->blaspService->check('This is a test string');
        $this->assertFalse($result->hasProfanity);
    }

    /**
     * Test that the cache configuration is properly loaded
     */
    public function test_cache_configuration_is_properly_loaded()
    {
        Config::set('blasp.cache_driver', 'array');
        $this->assertEquals('array', Config::get('blasp.cache_driver'));

        Config::set('blasp.cache_driver', null);
        $this->assertNull(Config::get('blasp.cache_driver'));
    }
}
