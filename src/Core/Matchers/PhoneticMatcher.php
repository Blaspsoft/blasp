<?php

namespace Blaspsoft\Blasp\Core\Matchers;

class PhoneticMatcher
{
    /** @var array<string, array<string>> metaphone code → list of profanity words */
    private array $index = [];

    public function __construct(
        array $profanities,
        private int $phonemes = 4,
        private int $minWordLength = 3,
        private float $maxDistanceRatio = 0.6,
        private array $phoneticFalsePositives = [],
    ) {
        $this->phoneticFalsePositives = array_map(fn($fp) => mb_strtolower($fp, 'UTF-8'), $this->phoneticFalsePositives);
        $this->buildIndex($profanities);
    }

    private function buildIndex(array $profanities): void
    {
        foreach ($profanities as $word) {
            $lower = mb_strtolower($word, 'UTF-8');
            if (mb_strlen($lower, 'UTF-8') < $this->minWordLength) {
                continue;
            }

            $code = metaphone($lower, $this->phonemes);
            if ($code === '') {
                continue;
            }

            $this->index[$code][] = $lower;
        }

        // Deduplicate
        foreach ($this->index as $code => $words) {
            $this->index[$code] = array_values(array_unique($words));
        }
    }

    public function match(string $word): ?string
    {
        $lower = strtolower($word);

        if (mb_strlen($lower, 'UTF-8') < $this->minWordLength) {
            return null;
        }

        if (in_array($lower, $this->phoneticFalsePositives, true)) {
            return null;
        }

        $code = metaphone($lower, $this->phonemes);
        if ($code === '' || !isset($this->index[$code])) {
            return null;
        }

        $bestMatch = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($this->index[$code] as $profanity) {
            $distance = levenshtein($lower, $profanity);
            $maxLen = max(mb_strlen($lower, 'UTF-8'), mb_strlen($profanity, 'UTF-8'));
            $threshold = (int) ceil($this->maxDistanceRatio * $maxLen);

            if ($distance <= $threshold && $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestMatch = $profanity;
            }
        }

        return $bestMatch;
    }
}
