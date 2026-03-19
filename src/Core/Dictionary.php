<?php

namespace Blaspsoft\Blasp\Core;

use Blaspsoft\Blasp\Enums\Severity;
use Blaspsoft\Blasp\Core\Matchers\RegexMatcher;
use Blaspsoft\Blasp\Core\Normalizers\StringNormalizer;
use Blaspsoft\Blasp\Core\Normalizers\EnglishNormalizer;
use Blaspsoft\Blasp\Core\Normalizers\SpanishNormalizer;
use Blaspsoft\Blasp\Core\Normalizers\GermanNormalizer;
use Blaspsoft\Blasp\Core\Normalizers\DutchNormalizer;
use Blaspsoft\Blasp\Core\Normalizers\FrenchNormalizer;
use Illuminate\Support\Facades\Cache;

class Dictionary
{
    private const CACHE_TTL = 86400;

    private array $profanities;
    private array $falsePositives;
    private array $separators;
    private array $substitutions;
    private array $severityMap;
    private array $profanityExpressions;
    private StringNormalizer $normalizer;
    private array $allowList;
    private array $blockList;
    private string $language;

    private static array $normalizers = [];

    public function __construct(
        array $profanities,
        array $falsePositives,
        array $separators,
        array $substitutions,
        array $severityMap,
        StringNormalizer $normalizer,
        array $allowList = [],
        array $blockList = [],
        string $language = 'english',
        ?array $profanityExpressions = null,
    ) {
        $this->profanities = $profanities;
        $this->falsePositives = $falsePositives;
        $this->separators = $separators;
        $this->substitutions = $substitutions;
        $this->severityMap = $severityMap;
        $this->normalizer = $normalizer;
        $this->allowList = array_map('strtolower', $allowList);
        $this->blockList = array_map('strtolower', $blockList);
        $this->language = $language;

        // Apply block list — add extra words to profanities
        foreach ($this->blockList as $word) {
            if (!in_array($word, $this->profanities)) {
                $this->profanities[] = $word;
                $this->severityMap[$word] = Severity::High;
            }
        }

        // Remove allow-listed words
        if (!empty($this->allowList)) {
            $this->profanities = array_values(array_filter(
                $this->profanities,
                fn($p) => !in_array(strtolower($p), $this->allowList)
            ));
        }

        if ($profanityExpressions !== null) {
            $this->profanityExpressions = $profanityExpressions;
        } else {
            $this->profanityExpressions = (new RegexMatcher())->generateExpressions(
                $this->profanities,
                $this->separators,
                $this->substitutions
            );
        }
    }

    public static function forLanguage(string $language, array $options = []): self
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $language)) {
            return new self(
                profanities: [],
                falsePositives: [],
                separators: [],
                substitutions: [],
                severityMap: [],
                normalizer: new EnglishNormalizer(),
                language: $language,
            );
        }

        $config = self::loadLanguageConfig($language);
        $globalConfig = self::loadGlobalConfig();

        $profanities = $config['profanities'] ?? [];
        $falsePositives = $config['false_positives'] ?? [];
        $severityMap = self::buildSeverityMap($config);

        $substitutions = $globalConfig['substitutions'] ?? [];
        if (isset($config['substitutions']) && is_array($config['substitutions'])) {
            foreach ($config['substitutions'] as $pattern => $values) {
                if (is_array($values)) {
                    $substitutions[$pattern] = array_values(array_unique(array_merge(
                        $substitutions[$pattern] ?? [],
                        $values
                    )));
                }
            }
        }

        return new self(
            profanities: $profanities,
            falsePositives: $falsePositives,
            separators: $globalConfig['separators'] ?? [],
            substitutions: $substitutions,
            severityMap: $severityMap,
            normalizer: self::getNormalizerForLanguage($language),
            allowList: $options['allow'] ?? [],
            blockList: $options['block'] ?? [],
            language: $language,
        );
    }

    public static function forLanguages(array $languages, array $options = []): self
    {
        $allProfanities = [];
        $allFalsePositives = [];
        $allSeverityMap = [];
        $globalConfig = self::loadGlobalConfig();
        $substitutions = $globalConfig['substitutions'] ?? [];

        foreach ($languages as $language) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $language)) {
                continue;
            }
            $config = self::loadLanguageConfig($language);
            $allProfanities = array_merge($allProfanities, $config['profanities'] ?? []);
            $allFalsePositives = array_merge($allFalsePositives, $config['false_positives'] ?? []);
            $allSeverityMap = array_merge($allSeverityMap, self::buildSeverityMap($config));

            // Merge accent/diacritic substitutions only
            if (isset($config['substitutions']) && is_array($config['substitutions'])) {
                foreach ($config['substitutions'] as $pattern => $values) {
                    if (is_array($values)) {
                        $plainKey = trim($pattern, '/');
                        if (mb_strlen($plainKey, 'UTF-8') > 1 || preg_match('/^[a-zA-Z]$/', $plainKey)) {
                            continue;
                        }
                        $substitutions[$pattern] = array_values(array_unique(array_merge(
                            $substitutions[$pattern] ?? [],
                            $values
                        )));
                    }
                }
            }
        }

        return new self(
            profanities: array_values(array_unique($allProfanities)),
            falsePositives: array_values(array_unique($allFalsePositives)),
            separators: $globalConfig['separators'] ?? [],
            substitutions: $substitutions,
            severityMap: $allSeverityMap,
            normalizer: self::getNormalizerForLanguage('english'),
            allowList: $options['allow'] ?? [],
            blockList: $options['block'] ?? [],
            language: implode(',', $languages),
        );
    }

    public static function forAllLanguages(array $options = []): self
    {
        $languages = self::getAvailableLanguages();
        return self::forLanguages($languages, $options);
    }

    public function getProfanities(): array
    {
        return $this->profanities;
    }

    public function getFalsePositives(): array
    {
        return $this->falsePositives;
    }

    public function getProfanityExpressions(): array
    {
        return $this->profanityExpressions;
    }

    public function getSeverity(string $word): Severity
    {
        $lower = strtolower($word);
        return $this->severityMap[$lower] ?? Severity::High;
    }

    public function getNormalizer(): StringNormalizer
    {
        return $this->normalizer;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getSeparators(): array
    {
        return $this->separators;
    }

    public function getSubstitutions(): array
    {
        return $this->substitutions;
    }

    // --- Static helpers ---

    public static function getAvailableLanguages(): array
    {
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

    public static function loadLanguageConfig(string $language): array
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $language)) {
            return ['profanities' => [], 'false_positives' => []];
        }

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
            return ['profanities' => [], 'false_positives' => []];
        }

        $config = require $languageFile;

        if (!is_array($config) || !isset($config['profanities'])) {
            return ['profanities' => [], 'false_positives' => []];
        }

        return $config;
    }

    private static function loadGlobalConfig(): array
    {
        return [
            'separators' => config('blasp.separators', config('blasp.drivers.regex.separators', [])),
            'substitutions' => config('blasp.substitutions', config('blasp.drivers.regex.substitutions', [])),
            'false_positives' => config('blasp.false_positives', []),
        ];
    }

    private static function buildSeverityMap(array $config): array
    {
        $map = [];

        if (isset($config['severity']) && is_array($config['severity'])) {
            foreach ($config['severity'] as $level => $words) {
                $severity = Severity::tryFrom($level) ?? Severity::High;
                foreach ($words as $word) {
                    $map[strtolower($word)] = $severity;
                }
            }
        }

        // Words only in profanities (not in severity map) default to High
        if (isset($config['profanities'])) {
            foreach ($config['profanities'] as $word) {
                $lower = strtolower($word);
                if (!isset($map[$lower])) {
                    $map[$lower] = Severity::High;
                }
            }
        }

        return $map;
    }

    public static function getNormalizerForLanguage(string $language): StringNormalizer
    {
        if (!isset(self::$normalizers[$language])) {
            self::$normalizers[$language] = match (strtolower($language)) {
                'english' => new EnglishNormalizer(),
                'spanish' => new SpanishNormalizer(),
                'german' => new GermanNormalizer(),
                'french' => new FrenchNormalizer(),
                'dutch' => new DutchNormalizer(),
                default => new EnglishNormalizer(),
            };
        }

        return self::$normalizers[$language];
    }

    // --- Caching ---

    public static function clearCache(): void
    {
        $cache = self::getCache();
        $keys = $cache->get('blasp_cache_keys', []);

        foreach ($keys as $key) {
            $cache->forget($key);
        }

        $cache->forget('blasp_cache_keys');

        // Also clear result cache keys
        $resultKeys = $cache->get('blasp_result_cache_keys', []);

        foreach ($resultKeys as $key) {
            $cache->forget($key);
        }

        $cache->forget('blasp_result_cache_keys');
    }

    private static function getCache(): \Illuminate\Contracts\Cache\Repository
    {
        $driver = config('blasp.cache.driver', config('blasp.cache_driver'));

        return $driver !== null ? Cache::store($driver) : Cache::store();
    }
}
