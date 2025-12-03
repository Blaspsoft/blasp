<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Config\ConfigurationLoader;
use Blaspsoft\Blasp\Contracts\ExpressionGeneratorInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class CacheDriverConfigurationTest extends TestCase
{
    private ConfigurationLoader $loader;
    private ExpressionGeneratorInterface $mockExpressionGenerator;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockExpressionGenerator = $this->createMock(ExpressionGeneratorInterface::class);
        $this->mockExpressionGenerator->method('generateExpressions')->willReturn([]);

        $this->loader = new ConfigurationLoader($this->mockExpressionGenerator);

        // Clear cache before each test
        Cache::flush();
    }

    public function test_default_cache_driver_is_used_when_not_configured(): void
    {
        Config::set('blasp.cache_driver', null);

        $config = $this->loader->load(['test'], ['false_positive']);

        $this->assertNotNull($config);
        $this->assertTrue(Cache::has('blasp_cache_keys'));
    }

    public function test_custom_cache_driver_is_used_when_configured(): void
    {
        // Use the array driver as a test cache store
        Config::set('blasp.cache_driver', 'array');

        $config = $this->loader->load(['custom_test'], ['custom_false']);

        $this->assertNotNull($config);
        // Verify caching worked with the custom driver
        $keys = Cache::store('array')->get('blasp_cache_keys', []);
        $this->assertNotEmpty($keys);
    }

    public function test_cache_clear_uses_configured_driver(): void
    {
        Config::set('blasp.cache_driver', 'array');

        // Load and cache a configuration
        $this->loader->load(['test'], ['false_positive']);

        // Verify something is cached
        $keys = Cache::store('array')->get('blasp_cache_keys', []);
        $this->assertNotEmpty($keys);

        // Clear cache
        ConfigurationLoader::clearCache();

        // Verify cache is cleared
        $keys = Cache::store('array')->get('blasp_cache_keys', []);
        $this->assertEmpty($keys);
    }

    public function test_configuration_is_cached_with_custom_driver(): void
    {
        Config::set('blasp.cache_driver', 'array');

        // Load configuration first time
        $config1 = $this->loader->load(['test_prof'], ['test_false']);

        // Create a new loader
        $mockGenerator2 = $this->createMock(ExpressionGeneratorInterface::class);
        $mockGenerator2->method('generateExpressions')->willReturn(['different' => 'result']);
        $loader2 = new ConfigurationLoader($mockGenerator2);

        // Load configuration second time - should come from cache
        $config2 = $loader2->load(['test_prof'], ['test_false']);

        // Both configs should have the same data (from cache)
        $this->assertEquals($config1->getProfanities(), $config2->getProfanities());
        $this->assertEquals($config1->getFalsePositives(), $config2->getFalsePositives());
    }

    public function test_cache_keys_are_tracked_with_custom_driver(): void
    {
        Config::set('blasp.cache_driver', 'array');

        // Load multiple configurations
        $this->loader->load(['prof1'], ['false1']);
        $this->loader->load(['prof2'], ['false2']);

        // Verify cache keys are tracked in the custom driver
        $cacheKeys = Cache::store('array')->get('blasp_cache_keys', []);
        $this->assertGreaterThan(0, count($cacheKeys));

        // All tracked keys should exist in the custom cache store
        foreach ($cacheKeys as $key) {
            $this->assertTrue(
                Cache::store('array')->has($key),
                "Cache key {$key} should exist in array store"
            );
        }
    }

    public function test_switching_cache_driver_clears_from_correct_store(): void
    {
        // First, cache with array driver
        Config::set('blasp.cache_driver', 'array');
        $this->loader->load(['test1'], ['false1']);

        $arrayKeys = Cache::store('array')->get('blasp_cache_keys', []);
        $this->assertNotEmpty($arrayKeys);

        // Clear cache (should clear from array store)
        ConfigurationLoader::clearCache();

        // Verify array store is cleared
        $arrayKeys = Cache::store('array')->get('blasp_cache_keys', []);
        $this->assertEmpty($arrayKeys);
    }
}
