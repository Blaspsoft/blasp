<?php

namespace Blaspsoft\Blasp\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;

trait BlaspCache
{
    /**
     * Cache key prefix for profanity expressions
     */
    private const CACHE_KEY_PREFIX = 'blasp_profanity_expressions_';

    /**
     * Cache TTL in seconds (24 hours by default)
     */
    private const CACHE_TTL = 86400;

    /**
     * Get the cache instance with the configured driver
     */
    private static function getCache()
    {
        $driver = Config::get('blasp.cache_driver');
        return $driver ? Cache::driver($driver) : Cache::getFacadeRoot();
    }

    /**
     * Try to load configuration from cache, otherwise generate and cache it
     */
    private function loadFromCacheOrGenerate(): void
    {
        $cacheKey = $this->generateCacheKey();
        $cache = self::getCache();

        $cached = $cache->get($cacheKey);
        if ($cached) {
            $this->loadFromCache($cached);
            return;
        }

        $this->loadUncachedConfiguration();
        $this->cacheConfiguration($cacheKey);
    }

    /**
     * Generate a unique cache key based on the content
     */
    private function generateCacheKey(): string
    {
        $contentHash = md5(json_encode([
            'profanities' => $this->profanities,
            'falsePositives' => $this->falsePositives,
        ]));

        return self::CACHE_KEY_PREFIX . $contentHash;
    }

    /**
     * Cache the current configuration
     */
    private function cacheConfiguration(string $cacheKey): void
    {
        $configToCache = [
            'profanities' => $this->profanities,
            'separators' => $this->separators,
            'substitutions' => $this->substitutions,
            'falsePositives' => $this->falsePositives,
            'profanityExpressions' => $this->profanityExpressions,
            'separatorExpression' => $this->separatorExpression,
            'characterExpressions' => $this->characterExpressions,
        ];

        $cache = self::getCache();
        $cache->put($cacheKey, $configToCache, self::CACHE_TTL);
        $this->trackCacheKey($cacheKey);
    }

    /**
     * Load configuration from cache
     */
    private function loadFromCache(array $cached): void
    {
        $this->profanities = $cached['profanities'];
        $this->separators = $cached['separators'];
        $this->substitutions = $cached['substitutions'];
        $this->falsePositives = $cached['falsePositives'];
        $this->profanityExpressions = $cached['profanityExpressions'];
        $this->separatorExpression = $cached['separatorExpression'];
        $this->characterExpressions = $cached['characterExpressions'];
    }

    /**
     * Track cache key for later cleanup
     */
    private function trackCacheKey(string $cacheKey): void
    {
        $cache = self::getCache();
        $keys = $cache->get('blasp_cache_keys', []);

        if (!in_array($cacheKey, $keys)) {
            $keys[] = $cacheKey;
            $cache->put('blasp_cache_keys', $keys, self::CACHE_TTL);
        }
    }

    /**
     * Clear all Blasp caches
     */
    public static function clearCache(): void
    {
        $cache = self::getCache();

        $keys = $cache->get('blasp_cache_keys', []);
        foreach ($keys as $key) {
            $cache->forget($key);
        }

        $cache->forget('blasp_cache_keys');
    }
}
