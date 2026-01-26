<?php

namespace Blaspsoft\Blasp\Config;

use Illuminate\Support\Facades\Cache;
use Blaspsoft\Blasp\Contracts\DetectionConfigInterface;
use Blaspsoft\Blasp\Contracts\MultiLanguageConfigInterface;
use Blaspsoft\Blasp\Contracts\ExpressionGeneratorInterface;

/**
 * Configuration loader with caching support for profanity detection.
 * 
 * Handles loading and caching of detection configurations, including 
 * single-language and multi-language configurations with automatic 
 * cache invalidation and optimization.
 * 
 * @package Blaspsoft\Blasp\Config
 * @author Blasp Package
 * @since 3.0.0
 */
class ConfigurationLoader
{
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Get the cache store instance with the configured driver.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    private static function getCache(): \Illuminate\Contracts\Cache\Repository
    {
        $driver = config('blasp.cache_driver');

        return $driver !== null ? Cache::store($driver) : Cache::store();
    }

    public function __construct(
        private ?ExpressionGeneratorInterface $expressionGenerator = null
    ) {
    }

    /**
     * Load configuration with optional custom profanities and false positives.
     *
     * @param array|null $customProfanities
     * @param array|null $customFalsePositives
     * @param string|null $language
     * @return DetectionConfigInterface
     */
    public function load(?array $customProfanities = null, ?array $customFalsePositives = null, ?string $language = null): DetectionConfigInterface
    {
        // Determine which language to load
        $targetLanguage = $language ?? config('blasp.default_language', 'english');
        
        $profanities = $customProfanities;
        $falsePositives = $customFalsePositives;
        
        if ($profanities === null) {
            try {
                $languageData = $this->loadLanguage($targetLanguage);
                $profanities = $languageData['profanities'] ?? [];
                if (empty($profanities)) {
                    throw new \Exception("No profanities found in {$targetLanguage} language file");
                }
            } catch (\Exception $e) {
                // Fall back to config file
                $profanities = config('blasp.profanities');
            }
        }
        
        if ($falsePositives === null) {
            try {
                $languageData = $this->loadLanguage($targetLanguage);
                $falsePositives = $languageData['false_positives'] ?? [];
            } catch (\Exception $e) {
                // Fall back to config file
                $falsePositives = config('blasp.false_positives');
            }
        }

        $separators = config('blasp.separators');

        // Load substitutions - start with main config, then merge language-specific
        $substitutions = config('blasp.substitutions');
        try {
            $languageData = $this->loadLanguage($targetLanguage);
            if (isset($languageData['substitutions']) && is_array($languageData['substitutions'])) {
                // Only merge substitutions for basic a-z letters to avoid regex conflicts.
                // Accented character patterns (like /ù/, /é/) can cause issues when they
                // match inside already-substituted character classes.
                foreach ($languageData['substitutions'] as $pattern => $values) {
                    // Only include patterns for single ASCII letters: /a/ through /z/
                    if (preg_match('#^/[a-z]/$#i', $pattern)) {
                        $substitutions[$pattern] = $values;
                    }
                }
            }
        } catch (\Exception $e) {
            // Keep main config substitutions
        }

        $config = new DetectionConfig(
            $profanities,
            $falsePositives,
            $separators,
            $substitutions,
            $this->expressionGenerator
        );

        return $this->loadFromCacheOrGenerate($config);
    }

    /**
     * Load multi-language configuration.
     *
     * @param array $languageData
     * @param string $defaultLanguage
     * @return MultiLanguageConfigInterface
     */
    public function loadMultiLanguage(array $languageData = [], string $defaultLanguage = 'english'): MultiLanguageConfigInterface
    {
        // If no language data provided, load from language files
        if (empty($languageData)) {
            $languageData = $this->loadLanguageFiles();
        }

        $separators = config('blasp.separators');

        // For multi-language mode, use main config substitutions which are designed
        // to work across languages. Language-specific substitutions could cause
        // regex conflicts when combined.
        $substitutions = config('blasp.substitutions');

        $config = new MultiLanguageDetectionConfig(
            $languageData,
            $separators,
            $substitutions,
            $defaultLanguage,
            $this->expressionGenerator
        );

        return $this->loadFromCacheOrGenerate($config);
    }

    /**
     * Load all available language files from the languages directory.
     *
     * @return array
     */
    private function loadLanguageFiles(): array
    {
        $languageData = [];
        
        // Try multiple possible paths for the languages directory
        $possiblePaths = [
            config_path('languages'),
            __DIR__ . '/../../config/languages',
            realpath(__DIR__ . '/../../config/languages'),
        ];
        
        $languagesPath = null;
        foreach ($possiblePaths as $path) {
            if ($path && is_dir($path)) {
                $languagesPath = $path;
                break;
            }
        }
        
        if (!$languagesPath) {
            // Fallback to original config structure
            return [
                'english' => [
                    'profanities' => config('blasp.profanities'),
                    'false_positives' => config('blasp.false_positives')
                ]
            ];
        }

        $languageFiles = glob($languagesPath . '/*.php');
        
        foreach ($languageFiles as $languageFile) {
            $languageName = basename($languageFile, '.php');
            $languageConfig = require $languageFile;
            
            if (is_array($languageConfig) && 
                isset($languageConfig['profanities']) && 
                isset($languageConfig['false_positives'])) {
                $languageData[$languageName] = $languageConfig;
            }
        }

        // Ensure English is available as fallback
        if (empty($languageData['english'])) {
            $languageData['english'] = [
                'profanities' => config('blasp.profanities', []),
                'false_positives' => config('blasp.false_positives', [])
            ];
        }

        return $languageData;
    }

    /**
     * Get list of available languages from language files.
     *
     * @return array
     */
    public function getAvailableLanguages(): array
    {
        // Try multiple possible paths for the languages directory
        $possiblePaths = [
            config_path('languages'),
            __DIR__ . '/../../config/languages',
            realpath(__DIR__ . '/../../config/languages'),
        ];
        
        $languagesPath = null;
        foreach ($possiblePaths as $path) {
            if ($path && is_dir($path)) {
                $languagesPath = $path;
                break;
            }
        }
        
        if (!$languagesPath) {
            return ['english'];
        }

        $languageFiles = glob($languagesPath . '/*.php');
        $languages = [];
        
        foreach ($languageFiles as $languageFile) {
            $languages[] = basename($languageFile, '.php');
        }

        return empty($languages) ? ['english'] : $languages;
    }

    /**
     * Load a specific language configuration.
     *
     * @param string $language
     * @return array|null
     */
    public function loadLanguage(string $language): ?array
    {
        // Try multiple possible paths for the language file
        $possiblePaths = [
            config_path("languages/{$language}.php"),
            __DIR__ . "/../../config/languages/{$language}.php",
            realpath(__DIR__ . "/../../config/languages/{$language}.php"),
        ];
        
        $languageFile = null;
        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                $languageFile = $path;
                break;
            }
        }
        
        if (!$languageFile) {
            return null;
        }

        $languageConfig = require $languageFile;
        
        if (!is_array($languageConfig) || 
            !isset($languageConfig['profanities']) || 
            !isset($languageConfig['false_positives'])) {
            return null;
        }

        return $languageConfig;
    }

    /**
     * Try to load configuration from cache, otherwise generate and cache it.
     *
     * @param DetectionConfigInterface $config
     * @return DetectionConfigInterface
     */
    private function loadFromCacheOrGenerate(DetectionConfigInterface $config): DetectionConfigInterface
    {
        $cacheKey = $config->getCacheKey();
        $cached = self::getCache()->get($cacheKey);
        
        if ($cached) {
            return $this->loadFromCache($cached);
        }

        $this->cacheConfiguration($config, $cacheKey);
        return $config;
    }

    /**
     * Load configuration from cache data.
     *
     * @param array $cached
     * @return DetectionConfigInterface
     */
    private function loadFromCache(array $cached): DetectionConfigInterface
    {
        // Check if this is a multi-language configuration
        if (isset($cached['language_data'])) {
            return new MultiLanguageDetectionConfig(
                $cached['language_data'],
                $cached['separators'],
                $cached['substitutions'],
                $cached['default_language'] ?? 'english',
                $this->expressionGenerator
            );
        }

        return new DetectionConfig(
            $cached['profanities'],
            $cached['falsePositives'],
            $cached['separators'],
            $cached['substitutions'],
            $this->expressionGenerator
        );
    }

    /**
     * Cache the configuration.
     *
     * @param DetectionConfigInterface $config
     * @param string $cacheKey
     * @return void
     */
    private function cacheConfiguration(DetectionConfigInterface $config, string $cacheKey): void
    {
        $configToCache = [
            'profanities' => $config->getProfanities(),
            'falsePositives' => $config->getFalsePositives(),
            'separators' => $config->getSeparators(),
            'substitutions' => $config->getSubstitutions(),
        ];

        // Add multi-language specific data if applicable
        if ($config instanceof MultiLanguageConfigInterface) {
            $languageData = [];
            foreach ($config->getAvailableLanguages() as $language) {
                $languageData[$language] = [
                    'profanities' => $config->getProfanitiesForLanguage($language),
                    'false_positives' => $config->getFalsePositivesForLanguage($language)
                ];
            }

            $configToCache['language_data'] = $languageData;
            $configToCache['default_language'] = $config->getCurrentLanguage();
        }

        self::getCache()->put($cacheKey, $configToCache, self::CACHE_TTL);
        $this->trackCacheKey($cacheKey);
    }

    /**
     * Track cache key for later cleanup.
     *
     * @param string $cacheKey
     * @return void
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
     * Clear all cached configurations.
     *
     * @return void
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