<?php

namespace Blaspsoft\Blasp;

use Exception;
use Blaspsoft\Blasp\Normalizers\Normalize;
use Blaspsoft\Blasp\Abstracts\StringNormalizer;

class BlaspService extends BlaspExpressionService
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
     * Language the package should use
     *
     * @var string|null
     */
    protected ?string $chosenLanguage;

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
     * Initialise the class and parent class.
     *
     */
    public function __construct(?array $profanities = null, ?array $falsePositives = null)
    {
        parent::__construct($profanities, $falsePositives);

        $this->profanityDetector = new ProfanityDetector($this->profanityExpressions, $this->falsePositives);

        $this->stringNormalizer =  Normalize::getLanguageNormalizerInstance();
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
        $blasp = new BlaspService($profanities, $falsePositives);

        return $blasp;
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

		$this->hasProfanity = false;

        $this->uniqueProfanitiesFound = [];

        $this->profanitiesCount = 0;

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

        $stringToNormalize = $this->cleanString;
        $normalizedString = $this->stringNormalizer->normalize($stringToNormalize);

        // Loop through until no more profanities are detected
        while ($continue) {
            $continue = false;
            $normalizedString = preg_replace('/\s+/', ' ', $normalizedString);
            foreach ($this->profanityDetector->getProfanityExpressions() as $profanity => $expression) {
                preg_match_all($expression, $normalizedString, $matches, PREG_OFFSET_CAPTURE);

                if (!empty($matches[0])) {
                    foreach ($matches[0] as $match) {
                        // Get the start and length of the match
                        $start = $match[1];
                        $length = strlen($match[0]);

                        // Use boundaries to extract the full word around the match
                        $fullWord = $this->getFullWordContext($normalizedString, $start, $length);

                        // Check if the full word (in lowercase) is in the false positives list
                        if ($this->profanityDetector->isFalsePositive($fullWord)) {
                            continue;  // Skip checking this word if it's a false positive
                        }

                        $continue = true;  // Continue if we find any profanities

                        $this->hasProfanity = true;

                        // Replace the found profanity
                        $this->generateProfanityReplacement((array) $match);

                        $normalizedString = substr_replace($normalizedString, str_repeat('*', $length), $start, $length);

                        // Avoid adding duplicates to the unique list
                        if (!in_array($profanity, $this->uniqueProfanitiesFound)) {
                            $this->uniqueProfanitiesFound[] = $profanity;
                        }
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Mask the profanities found in the incoming string.
     *
     * @param array $match
     * @return void
     */
    private function generateProfanityReplacement(array $match): void
    {
        $start = $match[1]; // Starting position of the profanity
        $length = mb_strlen($match[0], 'UTF-8'); // Length of the profanity
        $replacement = str_repeat("*", $length); // Mask with asterisks

        // Replace only the profanity in the cleanString, preserving the original case and spaces
        $this->cleanString = mb_substr($this->cleanString, 0, $start) . $replacement .
            mb_substr($this->cleanString, $start + $length);

        // Increment profanity count
        $this->profanitiesCount++;
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