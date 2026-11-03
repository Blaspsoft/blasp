<?php

namespace Blaspsoft\Blasp\Core\Matchers;

class FalsePositiveFilter
{
    private array $falsePositivesMap;

    public function __construct(array $falsePositives)
    {
        $this->falsePositivesMap = array_flip(array_map('strtolower', $falsePositives));
    }

    public function isFalsePositive(string $word): bool
    {
        return isset($this->falsePositivesMap[strtolower($word)]);
    }

    public function isInsideHexToken(string $string, int $start, int $length): bool
    {
        $end = $start + $length;
        $strLen = strlen($string);

        $tokenStart = $start;
        while ($tokenStart > 0 && preg_match('/[0-9a-fA-F\-]/', $string[$tokenStart - 1])) {
            $tokenStart--;
        }

        $tokenEnd = $end;
        while ($tokenEnd < $strLen && preg_match('/[0-9a-fA-F\-]/', $string[$tokenEnd])) {
            $tokenEnd++;
        }

        $token = substr($string, $tokenStart, $tokenEnd - $tokenStart);
        $token = trim($token, '-');

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

        $matchStartChar = mb_strlen(substr($fullString, 0, $matchStart), 'UTF-8');
        $matchEndChar = $matchStartChar + mb_strlen($matchedText, 'UTF-8');

        $embeddedAtStart = false;
        $embeddedAtEnd = false;

        if ($matchStartChar > 0) {
            $charBefore = mb_substr($fullString, $matchStartChar - 1, 1, 'UTF-8');
            if (preg_match('/\w/u', $charBefore)) {
                $embeddedAtStart = true;
            }
        }

        if ($matchEndChar < mb_strlen($fullString, 'UTF-8')) {
            $charAfter = mb_substr($fullString, $matchEndChar, 1, 'UTF-8');
            if (preg_match('/\w/u', $charAfter)) {
                $embeddedAtEnd = true;
            }
        }

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
}
