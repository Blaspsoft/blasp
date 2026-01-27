<?php

namespace Blaspsoft\Blasp;

use Exception;
use Blaspsoft\Blasp\Normalizers\Normalize;
use Blaspsoft\Blasp\Abstracts\StringNormalizer;
use Blaspsoft\Blasp\Contracts\DetectionConfigInterface;
use Blaspsoft\Blasp\Config\ConfigurationLoader;

class BlaspService
{
    /**
     * The incoming string to check for profanities.
     *
     * @var string
     */
    public string $sourceString = '';

    /**
     * The sanitised string with profanities masked.
     *
     * @var string
     */
    public string $cleanString = '';

    /**
     * A boolean value indicating if the incoming string
     * contains any profanities.
     *
     * @var bool
     */
    public bool $hasProfanity = false;

    /**
     * The number of profanities found in the incoming string.
     *
     * @var int
     */
    public int $profanitiesCount = 0;

    /**
     * An array of unique profanities found in the incoming string.
     *
     * @var array
     */
    public array $uniqueProfanitiesFound = [];

    /**
     * Hash map for O(1) unique profanity tracking.
     *
     * @var array
     */
    private array $uniqueProfanitiesMap = [];

    /**
     * Language the package should use
     *
     * @var string|null
     */
    protected ?string $chosenLanguage = null;

    /**
     * Detection configuration instance.
     *
     * @var DetectionConfigInterface
     */
    private DetectionConfigInterface $config;

    /**
     * Configuration loader instance.
     *
     * @var ConfigurationLoader
     */
    private ConfigurationLoader $configurationLoader;

    /**
     * Profanity detector instance.
     *
     * @var ProfanityDetector
     */
    private ProfanityDetector $profanityDetector;

    /**
     * String normalizer instance.
     *
     * @var StringNormalizer
     */
    private StringNormalizer $stringNormalizer;

    /**
     * Custom mask character to use for censoring.
     *
     * @var string|null
     */
    protected ?string $customMaskCharacter = null;

    /**
     * Initialise the class.
     *
     */
    public function __construct(
        ?array $profanities = null,
        ?array $falsePositives = null,
        ?ConfigurationLoader $configurationLoader = null
    ) {
        $this->configurationLoader = $configurationLoader ?? new ConfigurationLoader();
        
        // Set default language from config if not specified
        if (!$this->chosenLanguage) {
            $this->chosenLanguage = config('blasp.default_language', 'english');
        }
        
        $this->config = $this->configurationLoader->load($profanities, $falsePositives, $this->chosenLanguage);

        $this->profanityDetector = new ProfanityDetector(
            $this->config->getProfanityExpressions(),
            $this->config->getFalsePositives()
        );

        $this->stringNormalizer = Normalize::getLanguageNormalizerInstance();
    }

    /**
     * Configure the profanities and false positives.
     *
     * @param array|null $profanities
     * @param array|null $falsePositives
     * @return self
     */
    public function configure(?array $profanities = null, ?array $falsePositives = null): self
    {
        $newInstance = clone $this;
        $newInstance->config = $newInstance->configurationLoader->load($profanities, $falsePositives, $newInstance->chosenLanguage);
        $newInstance->profanityDetector = new ProfanityDetector(
            $newInstance->config->getProfanityExpressions(),
            $newInstance->config->getFalsePositives()
        );

        return $newInstance;
    }

    /**
     * Set the language for profanity detection
     *
     * @param string $language
     * @return self
     * @throws \InvalidArgumentException
     */
    public function language(string $language): self
    {
        $newInstance = clone $this;
        $newInstance->chosenLanguage = $language;
        
        try {
            // Reload configuration for the new language
            $newInstance->config = $newInstance->configurationLoader->load(null, null, $language);
            $newInstance->profanityDetector = new ProfanityDetector(
                $newInstance->config->getProfanityExpressions(),
                $newInstance->config->getFalsePositives()
            );
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Failed to load language '{$language}': " . $e->getMessage());
        }
        
        return $newInstance;
    }

    /**
     * Set English language (shortcut method)
     *
     * @return self
     */
    public function english(): self
    {
        return $this->language('english');
    }

    /**
     * Set Spanish language (shortcut method)
     *
     * @return self
     */
    public function spanish(): self
    {
        return $this->language('spanish');
    }

    /**
     * Set German language (shortcut method)
     *
     * @return self
     */
    public function german(): self
    {
        return $this->language('german');
    }

    /**
     * Set French language (shortcut method)
     *
     * @return self
     */
    public function french(): self
    {
        return $this->language('french');
    }

    /**
     * Set custom mask character for censoring profanities
     *
     * @param string $character
     * @return self
     * @throws \InvalidArgumentException
     */
    public function maskWith(string $character): self
    {
        if (empty($character)) {
            throw new \InvalidArgumentException('Mask character cannot be empty');
        }
        
        $newInstance = clone $this;
        $newInstance->customMaskCharacter = mb_substr($character, 0, 1); // Ensure single character
        return $newInstance;
    }

    /**
     * Enable checking against all available languages
     *
     * @return self
     */
    public function allLanguages(): self
    {
        $newInstance = clone $this;
        $newInstance->chosenLanguage = 'all';
        
        // Load multi-language configuration with all available languages
        // Pass 'all' as the default language to trigger all-language mode
        $newInstance->config = $newInstance->configurationLoader->loadMultiLanguage([], 'all');
        $newInstance->profanityDetector = new ProfanityDetector(
            $newInstance->config->getProfanityExpressions(),
            $newInstance->config->getFalsePositives()
        );
        
        return $newInstance;
    }

    /**
     * @param string $string
     * @return $this
     * @throws Exception
     */
    public function check(string $string): self
    {
        if (empty($string)) {

            throw new Exception('No string to check');
        }

        $this->sourceString = $string;

        $this->cleanString = $string;

        // Reset tracking variables
        $this->hasProfanity = false;
        $this->profanitiesCount = 0;
        $this->uniqueProfanitiesFound = [];
        $this->uniqueProfanitiesMap = [];

        $this->handle();

        return $this;
    }

    /**
     * Check if the incoming string contains any profanities, set property
     * values and mask the profanities within the incoming string.
     *
     * @return $this
     */
    private function handle(): self
    {
        $continue = true;

        // Work with a copy of cleanString that we'll modify in sync with normalized string
        $workingCleanString = $this->cleanString;
        $normalizedString = $this->stringNormalizer->normalize($workingCleanString);

        // Loop through until no more profanities are detected
        while ($continue) {
            $continue = false;
            $normalizedString = preg_replace('/\s+/', ' ', $normalizedString);
            $workingCleanString = preg_replace('/\s+/', ' ', $workingCleanString);
            
            foreach ($this->profanityDetector->getProfanityExpressions() as $profanity => $expression) {
                preg_match_all($expression, $normalizedString, $matches, PREG_OFFSET_CAPTURE);

                if (!empty($matches[0])) {
                    foreach ($matches[0] as $match) {
                        // Get the start and length of the match
                        $start = $match[1];
                        $length = mb_strlen($match[0], 'UTF-8');
                        $matchedText = $match[0];

                        // Check if the match inappropriately spans across word boundaries
                        if ($this->isSpanningWordBoundary($matchedText)) {
                            continue;  // Skip this match as it spans word boundaries
                        }

                        // Check if the match is inside a hex/UUID token
                        if ($this->isInsideHexToken($normalizedString, $start, $length)) {
                            continue;
                        }

                        // Use boundaries to extract the full word around the match
                        $fullWord = $this->getFullWordContext($normalizedString, $start, $length);

                        // Check if the full word (in lowercase) is in the false positives list
                        if ($this->profanityDetector->isFalsePositive($fullWord)) {
                            continue;  // Skip checking this word if it's a false positive
                        }

                        $continue = true;  // Continue if we find any profanities

                        $this->hasProfanity = true;

                        // Replace the found profanity
                        $length = mb_strlen($match[0], 'UTF-8');
                        $maskChar = $this->customMaskCharacter ?? config('blasp.mask_character', '*');
                        $replacement = str_repeat($maskChar, $length);
                        
                        // Replace in working clean string
                        $workingCleanString = mb_substr($workingCleanString, 0, $start) . $replacement .
                            mb_substr($workingCleanString, $start + $length);

                        // Replace in normalized string to keep tracking consistent  
                        $normalizedString = mb_substr($normalizedString, 0, $start) . str_repeat($maskChar, mb_strlen($match[0], 'UTF-8')) .
                            mb_substr($normalizedString, $start + mb_strlen($match[0], 'UTF-8'));

                        // Increment profanity count
                        $this->profanitiesCount++;

                        // Avoid adding duplicates to the unique list using hash map for O(1) lookup
                        if (!isset($this->uniqueProfanitiesMap[$profanity])) {
                            $this->uniqueProfanitiesFound[] = $profanity;
                            $this->uniqueProfanitiesMap[$profanity] = true;
                        }
                    }
                }
            }
        }

        // Update the final clean string
        $this->cleanString = $workingCleanString;

        return $this;
    }

    /**
     * Check if a match falls inside a hex-like token (UUID, MD5, SHA hash, hex color, etc.).
     */
    private function isInsideHexToken(string $string, int $start, int $length): bool
    {
        $end = $start + $length;
        $strLen = strlen($string);

        // Expand left to find start of contiguous hex+hyphen token
        $tokenStart = $start;
        while ($tokenStart > 0 && preg_match('/[0-9a-fA-F\-]/', $string[$tokenStart - 1])) {
            $tokenStart--;
        }

        // Expand right
        $tokenEnd = $end;
        while ($tokenEnd < $strLen && preg_match('/[0-9a-fA-F\-]/', $string[$tokenEnd])) {
            $tokenEnd++;
        }

        $token = substr($string, $tokenStart, $tokenEnd - $tokenStart);

        // Trim leading/trailing hyphens
        $token = trim($token, '-');

        // If the token matches a UUID pattern, reject
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $token)) {
            return true;
        }

        // Strip hyphens and check for a long hex string containing digits
        $stripped = str_replace('-', '', $token);
        if (strlen($stripped) >= 8 && preg_match('/^[0-9a-fA-F]+$/', $stripped) && preg_match('/[0-9]/', $stripped)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether a matched substring inappropriately spans word boundaries.
     */
    private function isSpanningWordBoundary(string $matchedText): bool
    {
        // If the match contains spaces, it might be spanning word boundaries
        if (preg_match('/\s+/', $matchedText)) {
            $parts = preg_split('/\s+/', $matchedText);

            if (count($parts) > 1) {
                // Count how many parts are single characters
                $singleCharCount = 0;
                foreach ($parts as $part) {
                    if (strlen($part) === 1 && preg_match('/[a-z]/i', $part)) {
                        $singleCharCount++;
                    }
                }

                // If ALL parts are single characters, this is intentional obfuscation
                // (e.g., "f u c k i n g") - allow it
                if ($singleCharCount === count($parts)) {
                    return false;
                }

                // If SOME parts are single characters at edges, this is likely
                // a cross-word match (e.g., "t êt" from "pourrait être") - reject it
                $firstPart = $parts[0];
                $lastPart = end($parts);

                if (strlen($lastPart) === 1 && preg_match('/[a-z]/i', $lastPart)) {
                    return true;
                }

                if (strlen($firstPart) === 1 && preg_match('/[a-z]/i', $firstPart)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the full word context surrounding the matched profanity.
     *
     * @param string $string
     * @param int $start
     * @param int $length
     * @return string
     */
    private function getFullWordContext(string $string, int $start, int $length): string
    {
        // Define word boundaries (spaces, punctuation, etc.)
        $left = $start;
        $right = $start + $length;

        // Move the left pointer backwards to find the start of the full word
        while ($left > 0 && preg_match('/\w/', $string[$left - 1])) {
            $left--;
        }

        // Move the right pointer forwards to find the end of the full word
        while ($right < strlen($string) && preg_match('/\w/', $string[$right])) {
            $right++;
        }

        // Return the full word surrounding the matched profanity
        return substr($string, $left, $right - $left);
    }


    /**
     * Get the incoming string.
     *
     * @return string
     */
    public function getSourceString(): string
    {
        return $this->sourceString;
    }

    /**
     * Get the clean string with profanities masked.
     *
     * @return string
     */
    public function getCleanString(): string
    {
        return $this->cleanString;
    }

    /**
     * Get a boolean value indicating if the incoming
     * string contains any profanities.
     *
     * @return bool
     */
    public function hasProfanity(): bool
    {
        return $this->hasProfanity;
    }

    /**
     * Get the number of profanities found in the incoming string.
     *
     * @return int
     */
    public function getProfanitiesCount(): int
    {
        return $this->profanitiesCount;
    }

    /**
     * Get the unique profanities found in the incoming string.
     *
     * @return array
     */
    public function getUniqueProfanitiesFound(): array
    {
        return $this->uniqueProfanitiesFound;
    }
}