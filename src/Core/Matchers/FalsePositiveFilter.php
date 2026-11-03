<?php

namespace Blaspsoft\Blasp\Core\Matchers;

class FalsePositiveFilter
{
    // Common one- and two-letter words. A spaced-out match made only of real words is two
    // words, not an obfuscation; "sh" or "fu" are not words, so "sh it" still matches.
    private const SHORT_WORDS = [
        'a', 'i', 'o', 'am', 'an', 'as', 'at', 'be', 'by', 'do', 'go', 'he', 'if', 'in', 'is',
        'it', 'me', 'my', 'no', 'of', 'oh', 'ok', 'on', 'or', 'so', 'to', 'up', 'us', 'we',
    ];

    private array $falsePositivesMap;

    public function __construct(array $falsePositives)
    {
        $this->falsePositivesMap = array_flip(array_map('strtolower', $falsePositives));
    }

    public function isFalsePositive(string $word): bool
    {
        return isset($this->falsePositivesMap[strtolower($word)]);
    }

    // The last hex token seen, when it consisted only of hex digits and dashes. Every match
    // inside such a run sees the same token, so the answer is reused instead of rescanning
    // the run for each candidate (quadratic on a long hash-like token).
    private ?string $hexString = null;
    private int $hexLeft = -1;
    private int $hexRight = -1;
    private bool $hexResult = false;

    public function isInsideHexToken(string $string, int $start, int $length): bool
    {
        $end = $start + $length;

        if ($start >= $this->hexLeft && $end <= $this->hexRight && $string === $this->hexString) {
            return $this->hexResult;
        }

        $strLen = strlen($string);

        $tokenStart = $start;
        while ($tokenStart > 0 && self::isHexOrDash($string[$tokenStart - 1])) {
            $tokenStart--;
        }

        $tokenEnd = $end + strspn($string, '0123456789abcdefABCDEF-', min($end, $strLen));

        $rawToken = substr($string, $tokenStart, $tokenEnd - $tokenStart);
        $result = self::isHexToken(trim($rawToken, '-'));

        if (strspn($rawToken, '0123456789abcdefABCDEF-') === strlen($rawToken)) {
            $this->hexString = $string;
            $this->hexLeft = $tokenStart;
            $this->hexRight = $tokenEnd;
            $this->hexResult = $result;
        }

        return $result;
    }

    private static function isHexOrDash(string $char): bool
    {
        return $char === '-' || ctype_xdigit($char);
    }

    private static function isHexToken(string $token): bool
    {
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $token)) {
            return true;
        }

        $stripped = str_replace('-', '', $token);
        if (strlen($stripped) >= 8 && preg_match('/^[0-9a-fA-F]+$/', $stripped) && preg_match('/[0-9]/', $stripped)) {
            return true;
        }

        return false;
    }

    public function isSpanningWordBoundary(string $matchedText, string $fullString, int $matchStart): bool
    {
        if (!preg_match('/\s+/', $matchedText)) {
            return false;
        }

        $parts = preg_split('/\s+/', $matchedText);

        if (count($parts) <= 1) {
            return false;
        }

        $singleCharCount = 0;
        foreach ($parts as $part) {
            if (mb_strlen($part, 'UTF-8') === 1 && preg_match('/\p{L}/u', $part)) {
                $singleCharCount++;
            }
        }

        if ($singleCharCount === count($parts)) {
            return false;
        }

        $matchEnd = $matchStart + strlen($matchedText);

        // A word character, or an apostrophe inside a word ("let's", "it's"), next to the
        // match means that end of it belongs to a longer word
        $embeddedAtStart = self::continuesWord(self::charsBefore($fullString, $matchStart, 2));
        $embeddedAtEnd = self::continuesWord(self::charsAfter($fullString, $matchEnd, 2), true);

        if ($embeddedAtStart && $embeddedAtEnd) {
            return true;
        }

        if ($embeddedAtStart && !$embeddedAtEnd) {
            $standaloneParts = array_slice($parts, 1);
            $standalonePortion = implode(' ', $standaloneParts);

            // Accented letters are letters, not obfuscation ("fück a" is two words)
            $hasLetter = preg_match('/\p{L}/u', $standalonePortion);
            $hasNonLetter = preg_match('/[^\p{L}\p{M}\s]/u', $standalonePortion);

            if ($hasLetter && $hasNonLetter) {
                return false;
            }
            return true;
        }

        if (!$embeddedAtStart && $embeddedAtEnd) {
            $standaloneParts = array_slice($parts, 0, -1);
            $standalonePortion = implode(' ', $standaloneParts);

            // Accented letters are letters, not obfuscation ("fück a" is two words)
            $hasLetter = preg_match('/\p{L}/u', $standalonePortion);
            $hasNonLetter = preg_match('/[^\p{L}\p{M}\s]/u', $standalonePortion);

            if ($hasLetter && $hasNonLetter) {
                return false;
            }
            return true;
        }

        return false;
    }

    public function getFullWordContext(string $string, int $start, int $length): string
    {
        $left = $start;
        $right = $start + $length;

        while ($left > 0 && preg_match('/\w/', $string[$left - 1])) {
            $left--;
        }

        while ($right < strlen($string) && preg_match('/\w/', $string[$right])) {
            $right++;
        }

        return substr($string, $left, $right - $left);
    }

    /**
     * Whether a match is a word written with an apostrophe in a contraction ("he'll",
     * "she'll"): the apostrophe there is part of the word, not an obfuscating separator.
     */
    public function isContraction(string $matchedText, string $fullString, int $matchStart, string $profanity = ''): bool
    {
        // A list word written with an apostrophe ("nigger's") is matched as written
        if (!preg_match("/['\x{2019}]/u", $matchedText) || preg_match("/['\x{2019}]/u", $profanity)) {
            return false;
        }

        $matchEnd = $matchStart + strlen($matchedText);

        preg_match("/[\p{L}'\x{2019}]*$/u", substr($fullString, max(0, $matchStart - 64), min(64, $matchStart)), $before);
        preg_match("/^[\p{L}'\x{2019}]*/u", substr($fullString, $matchEnd, 64), $after);
        $word = ($before[0] ?? '') . $matchedText . ($after[0] ?? '');

        return preg_match("/^\p{L}+['\x{2019}](?:s|ll|re|ve|d|t|m)$/iu", $word) === 1;
    }

    /**
     * Up to $count characters ending at byte offset $offset (nearest last).
     */
    private static function charsBefore(string $string, int $offset, int $count): string
    {
        $slice = substr($string, max(0, $offset - 4 * $count), min(4 * $count, $offset));

        return mb_substr($slice, -$count, null, 'UTF-8');
    }

    /**
     * Up to $count characters starting at byte offset $offset.
     */
    private static function charsAfter(string $string, int $offset, int $count): string
    {
        return mb_substr(substr($string, $offset, 4 * $count), 0, $count, 'UTF-8');
    }

    /**
     * Whether the characters next to a match continue a word: a word character, or an
     * apostrophe with a letter beyond it. $chars runs outward from the match.
     */
    private static function continuesWord(string $chars, bool $forward = false): bool
    {
        $chars = mb_str_split($chars, 1, 'UTF-8');
        if (!$forward) {
            $chars = array_reverse($chars);
        }

        $next = $chars[0] ?? '';
        if ($next !== '' && preg_match('/\w/u', $next)) {
            return true;
        }

        return ($next === "'" || $next === "\u{2019}") && preg_match('/\p{L}/u', $chars[1] ?? '') === 1;
    }

    /**
     * Whether a match spanning whitespace just joins ordinary clean words ("a hole",
     * "dam nation", "money shot"), rather than spelling out a word with spaces ("f u c k",
     * "sh it") or a phrase that contains a profanity ("fuck you", "bull shit").
     */
    public function isJoinOfCleanWords(string $matchedText, string $profanity, array $profanityExpressions): bool
    {
        if (!preg_match('/\s/u', $matchedText) || preg_match('/\s/u', $profanity)) {
            return false;
        }

        $parts = preg_split('/\s+/u', trim($matchedText), -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) < 2) {
            return false;
        }

        foreach ($parts as $part) {
            $lower = mb_strtolower($part, 'UTF-8');

            if (!preg_match('/^\p{L}+$/u', $part) || $this->isProfaneWord($lower, $profanityExpressions)) {
                return false;
            }

            if (mb_strlen($part, 'UTF-8') < 3 && !in_array($lower, self::SHORT_WORDS, true)) {
                return false;
            }
        }

        return true;
    }

    /** @var array<string, bool> */
    private array $profaneWords = [];

    /**
     * Whether a whole word is itself a list word, as written or obfuscated ("fück").
     */
    private function isProfaneWord(string $word, array $profanityExpressions): bool
    {
        if (isset($this->profaneWords[$word])) {
            return $this->profaneWords[$word];
        }

        $profane = isset($profanityExpressions[$word]);

        foreach ($profane ? [] : $profanityExpressions as $expression) {
            if (preg_match($expression, $word, $match) === 1 && $match[0] === $word) {
                $profane = true;
                break;
            }
        }

        return $this->profaneWords[$word] = $profane;
    }

    /**
     * Whether a match beginning with "*" is a run of asterisks rather than an obfuscated
     * word: it has no letters or digits, or a letter follows it inside the same word.
     */
    public function isWildcardRun(string $matchedText, string $fullString, int $matchStart): bool
    {
        if (!preg_match('/[\p{L}\p{N}]/u', $matchedText)) {
            return true;
        }

        return preg_match('/^\p{L}/u', self::charsAfter($fullString, $matchStart + strlen($matchedText), 1)) === 1;
    }
}
