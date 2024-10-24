<?php

namespace Blaspsoft\Blasp;

use Exception;

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
     * Language of the text passed as a parameter
     *
     * @var string
     */
    public string $language;

    /**
     * Initialise the class and parent class.
     *
     */
    public function __construct()
    {
        parent::__construct();
        return $this;
    }

    /**
     * @param string $string
     * @param string $language
     * @return $this
     * @throws Exception
     */
    public function check(string $string, string $language = 'en'): self
    {
        if (empty($string)) {

            throw new Exception('No string to check');
        }

        $this->sourceString = $string;

        $this->cleanString = $string;

        $this->language = $language;

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
        // Convert false positives to lowercase for case-insensitive comparison
        $falsePositives = array_map('strtolower', config('blasp.false_positives')[$this->language]);
        $continue = true;

        // Sort profanities by length (longer first) to match longer profanities first
        uksort($this->profanityExpressions, function($a, $b) {
            return strlen($b) - strlen($a);  // Sort by length, descending
        });

        // Loop through until no more profanities are detected
        while ($continue) {
            $continue = false;

            foreach ($this->profanityExpressions as $profanity => $expression) {
                preg_match_all($expression, $this->cleanString, $matches, PREG_OFFSET_CAPTURE);

                if (!empty($matches[0])) {
                    foreach ($matches[0] as $match) {
                        // Get the start and length of the match
                        $start = $match[1];
                        $length = strlen($match[0]);

                        // Use boundaries to extract the full word around the match
                        $fullWord = $this->getFullWordContext($this->cleanString, $start, $length);

                        // Check if the full word (in lowercase) is in the false positives list
                        if (in_array(strtolower($fullWord), $falsePositives, true)) {
                            continue;  // Skip checking this word if it's a false positive
                        }

                        $continue = true;  // Continue if we find any profanities

                        $this->hasProfanity = true;

                        // Replace the found profanity
                        $this->generateProfanityReplacement((array)$match);

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

        // Ensure we're replacing only the matched portion of the cleanString
        $this->cleanString = substr_replace($this->cleanString, $replacement, $start, $length);

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