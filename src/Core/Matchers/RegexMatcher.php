<?php

namespace Blaspsoft\Blasp\Core\Matchers;

class RegexMatcher
{
    private const SEPARATOR_PLACEHOLDER = '{!!}';
    private const ESCAPED_SEPARATOR_CHARACTERS = ['\s'];

    // Upper bound on how many characters one letter of a word may span ("fuuuuck",
    // "f**k"). An unbounded + backtracks quadratically over long runs of matching
    // characters, so a few KB of "aaaa…" or "o*o*…" could tie up a worker for seconds.
    // The Analyzer shortens runs of one character to the same length, so stretched
    // letters still match.
    public const MAX_LETTER_REPEAT = 8;

    // Built expressions for recent word lists. Building them for every check was about 90%
    // of the cost of checking a short text (~14 ms for English, ~74 ms for all languages).
    private const EXPRESSION_CACHE_SIZE = 16;

    /** @var array<string, array<string, string>> */
    private static array $expressionCache = [];

    /**
     * @param  bool  $flexiblePhrases  Treat a space in a list entry as a separator slot, so
     *                                 "sieg heil" also matches "sieg-heil" and "siegheil"
     */
    public function generateExpressions(array $profanities, array $separators, array $substitutions, bool $flexiblePhrases = false): array
    {
        $key = hash('xxh128', serialize([$profanities, $separators, $substitutions, $flexiblePhrases]));

        if (isset(self::$expressionCache[$key])) {
            return self::$expressionCache[$key];
        }

        $units = $this->generateSubstitutionUnits($substitutions);

        $profanityExpressions = [];

        foreach ($profanities as $profanity) {
            $profanityExpressions[$profanity] = $this->buildProfanityExpression($profanity, $units, $separators, $flexiblePhrases);
        }

        // Bounded so a long-running worker (Octane, queues) with many allow/block list
        // combinations does not grow without limit
        if (count(self::$expressionCache) >= self::EXPRESSION_CACHE_SIZE) {
            array_shift(self::$expressionCache);
        }

        return self::$expressionCache[$key] = $profanityExpressions;
    }

    public function generateSeparatorExpression(array $separators, bool $allowDot = true): string
    {
        $normalSeparators = array_filter($separators, fn($sep) => $sep !== '.');

        $pattern = $this->generateEscapedExpression($normalSeparators, self::ESCAPED_SEPARATOR_CHARACTERS, '');

        return '(?:' . $pattern . ($allowDot ? '|\.(?=[\p{L}\p{N}\p{M}_])' : '') . '){0,3}?';
    }

    public function generateSubstitutionExpressions(array $substitutions): array
    {
        $characterExpressions = [];

        foreach ($substitutions as $character => $substitutionOptions) {
            $hasMultiChar = false;
            foreach ($substitutionOptions as $option) {
                if (mb_strlen($option, 'UTF-8') > 1 && !preg_match('/^\\\\.$/u', $option)) {
                    $hasMultiChar = true;
                    break;
                }
            }

            if ($hasMultiChar) {
                $escaped = array_map(function ($opt) {
                    if (preg_match('/^\\\\.$/u', $opt)) {
                        return $opt;
                    }
                    return preg_quote($opt, '/');
                }, $substitutionOptions);
                $characterExpressions[$character] = '(?:' . implode('|', $escaped) . '){1,' . self::MAX_LETTER_REPEAT . '}' . self::SEPARATOR_PLACEHOLDER;
            } else {
                $characterExpressions[$character] = $this->generateEscapedExpression($substitutionOptions, [], '{1,' . self::MAX_LETTER_REPEAT . '}') . self::SEPARATOR_PLACEHOLDER;
            }
        }

        return $characterExpressions;
    }

    public function generateProfanityExpression(string $profanity, array $substitutionExpressions, string $separatorExpression): string
    {
        $plainSubstitutions = [];
        foreach ($substitutionExpressions as $pattern => $replacement) {
            $plainKey = trim($pattern, '/');
            $plainSubstitutions[$plainKey] = $replacement;
        }

        uksort($plainSubstitutions, fn($a, $b) => mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8'));

        $expression = '';
        $i = 0;
        $len = mb_strlen($profanity, 'UTF-8');

        while ($i < $len) {
            $matched = false;
            foreach ($plainSubstitutions as $key => $replacement) {
                $keyLen = mb_strlen($key, 'UTF-8');
                if ($i + $keyLen <= $len && mb_substr($profanity, $i, $keyLen, 'UTF-8') === $key) {
                    $expression .= $replacement;
                    $i += $keyLen;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $char = mb_substr($profanity, $i, 1, 'UTF-8');
                $expression .= preg_quote($char, '/');
                // Letters and combining marks with no substitution entry (e.g. Devanagari)
                // still get separator tolerance so "मा-दर-चोद" matches like "f-u-c-k"
                if (preg_match('/^[\p{L}\p{M}]$/u', $char)) {
                    $expression .= self::SEPARATOR_PLACEHOLDER;
                }
                $i++;
            }
        }

        $expression = str_replace(self::SEPARATOR_PLACEHOLDER, $separatorExpression, $expression);
        $expression = '/' . $expression . '/iu';

        return $expression;
    }

    /**
     * Each substitution key with its repeated character class and the single characters
     * the class accepts, for building expressions with narrowed separators.
     *
     * @return array<string, array{pattern: string, chars: array<int, string>}>
     */
    private function generateSubstitutionUnits(array $substitutions): array
    {
        $units = [];

        foreach ($this->generateSubstitutionExpressions($substitutions) as $pattern => $expression) {
            $chars = [];
            foreach ($substitutions[$pattern] as $option) {
                if (mb_strlen($option, 'UTF-8') === 1) {
                    $chars[] = $option;
                } elseif (preg_match('/^\\\\.$/u', $option)) {
                    $chars[] = substr($option, 1);
                }
            }

            $units[trim($pattern, '/')] = [
                'pattern' => str_replace(self::SEPARATOR_PLACEHOLDER, '', $expression),
                'chars' => $chars,
            ];
        }

        uksort($units, fn ($a, $b) => mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8'));

        return $units;
    }

    /**
     * Like generateProfanityExpression(), but the separators allowed before a letter leave
     * out characters that letter already accepts ("!" is an i). Those characters are still
     * matched, by the letter, but no longer two ways, which kept a long run of them
     * ("!!!!…" against "lickme") backtracking for seconds.
     */
    private function buildProfanityExpression(string $profanity, array $units, array $separators, bool $flexiblePhrases = false): string
    {
        // Split the word into substitution units and literal characters, longest key first
        $sequence = [];
        $i = 0;
        $len = mb_strlen($profanity, 'UTF-8');

        while ($i < $len) {
            foreach ($units as $key => $unit) {
                $keyLen = mb_strlen($key, 'UTF-8');
                if ($i + $keyLen <= $len && mb_substr($profanity, $i, $keyLen, 'UTF-8') === $key) {
                    $sequence[] = $unit;
                    $i += $keyLen;
                    continue 2;
                }
            }

            $char = mb_substr($profanity, $i, 1, 'UTF-8');

            if ($flexiblePhrases && preg_match('/^\s$/u', $char)) {
                // A letter unit already ends in a separator slot, which covers the space;
                // after anything else the slot is added here
                $previous = end($sequence);
                if ($previous === false || ($previous['literal'] ?? false)) {
                    $sequence[] = ['pattern' => $this->generateSeparatorExpression($separators), 'chars' => [], 'literal' => true];
                }
                $i++;
                continue;
            }

            // Letters and combining marks with no substitution entry (e.g. Devanagari) still
            // take separators, so "मा-दर-चोद" matches like "f-u-c-k"; other literals do not
            $sequence[] = [
                'pattern' => preg_quote($char, '/'),
                'chars' => [$char],
                'literal' => !preg_match('/^[\p{L}\p{M}]$/u', $char),
            ];
            $i++;
        }

        $expression = '';

        foreach ($sequence as $index => $unit) {
            $expression .= $unit['pattern'];

            if ($unit['literal'] ?? false) {
                continue;
            }

            $next = $sequence[$index + 1] ?? null;
            $excluded = $next === null || ($next['literal'] ?? false) ? [] : $next['chars'];
            $expression .= $this->generateSeparatorExpression(array_values(array_diff($separators, $excluded)), !in_array('.', $excluded, true));
        }

        return '/' . $expression . '/iu';
    }

    private function generateEscapedExpression(array $characters = [], array $escapedCharacters = [], string $quantifier = '*?'): string
    {
        $regex = $escapedCharacters;

        foreach ($characters as $character) {
            $regex[] = preg_quote($character, '/');
        }

        return '[' . implode('', $regex) . ']' . $quantifier;
    }
}
