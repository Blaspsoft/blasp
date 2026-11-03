<?php

namespace Blaspsoft\Blasp\Core\Matchers;

class PhoneticMatcher
{
    // Common English words that sound like list words. Applied on top of the configured
    // phonetic false positives, so published configs get them too.
    private const COMMON_WORDS = [
        'ace', 'aces', 'beach', 'beaches', 'cake', 'cakes', 'cant', "can't", 'coke', 'cook',
        'cooks', 'cooked', 'count', 'counts', 'deck', 'decks', 'dock', 'docks', 'dog', 'dogs',
        'duck', 'ducks', 'facts', 'fake', 'fig', 'figs', 'fog', 'fork', 'forks', 'fort',
        'forts', 'fox', 'great', 'hall', 'halls', 'heel', 'heels', 'here', 'hill', 'hills',
        'him', 'his', 'hole', 'holes', 'kick', 'kicks', 'lazy', 'news', 'nice', 'peace',
        'quick', 'sheet', 'sheets', 'shout', 'shut', 'was', 'where', 'witch', 'witches',
        'gutter', 'gutters', 'pizza', 'pizzas', 'shake', 'shakes', 'shaking', 'warehouse', 'warehouses',
    ];

    /**
     * Most edits allowed for a word of a given length, whatever the configured ratio:
     * a 4-letter word within 2 edits of a list word is usually a different, ordinary word
     * ("was" → "wuss", "nice" → "nazi").
     */
    private static function canonical(string $word): string
    {
        $word = strtr($word, ['ph' => 'f', 'ck' => 'k', 'q' => 'k', 'c' => 'k', 'z' => 's', 'wh' => 'w',
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's']);

        return preg_replace('/(.)\1+/', '$1', $word) ?? $word;
    }

    private static function maxEdits(int $length): int
    {
        return match (true) {
            $length <= 4 => 1,
            $length <= 7 => 2,
            default => 3,
        };
    }

    /** @var array<string, array<string>> metaphone code → list of profanity words */
    private array $index = [];

    public function __construct(
        array $profanities,
        private int $phonemes = 4,
        private int $minWordLength = 3,
        private float $maxDistanceRatio = 0.6,
        private array $phoneticFalsePositives = [],
    ) {
        $this->phoneticFalsePositives = array_map(
            fn($fp) => mb_strtolower($fp, 'UTF-8'),
            array_merge(self::COMMON_WORDS, $this->phoneticFalsePositives)
        );
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
            // Spelling tricks that sound the same ("phag", "kunt") count as no edit
            $distance = min(levenshtein($lower, $profanity), levenshtein(self::canonical($lower), self::canonical($profanity)));
            $maxLen = max(mb_strlen($lower, 'UTF-8'), mb_strlen($profanity, 'UTF-8'));
            $threshold = min((int) ceil($this->maxDistanceRatio * $maxLen), self::maxEdits(mb_strlen($lower, 'UTF-8')));

            if ($distance <= $threshold && $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestMatch = $profanity;
            }
        }

        return $bestMatch;
    }
}
