<?php

namespace Blaspsoft\Blasp\Core\Matchers;

class CompoundWordDetector
{
    private const SUFFIXES = ['s', 'es', 'ed', 'er', 'ers', 'est', 'ing', 'ings', 'ly', 'y'];

    public function isPureAlphaSubstring(string $matchedText, string $fullWord, string $profanityKey, array $profanityExpressions): bool
    {
        if (!preg_match('/^[a-zA-Z]+$/', $matchedText)) {
            return false;
        }

        // The word context spans accented and other Unicode letters ("classé"), so a
        // Latin match inside such a word is still judged as a compound
        if (!preg_match('/^[\p{L}\p{M}]+$/u', $fullWord)) {
            return false;
        }

        if (strlen($fullWord) <= strlen($matchedText)) {
            return false;
        }

        // Compare with repeated letters collapsed, so a stretched match inside a longer
        // word ("Hellllo", "classsy", "crapped") is still treated as embedded
        if (strlen(self::collapse($matchedText)) > strlen(self::collapse($profanityKey))) {
            return false;
        }

        $matchLower = strtolower($matchedText);
        $wordLower = strtolower($fullWord);

        foreach (self::SUFFIXES as $suffix) {
            if ($wordLower === $matchLower . $suffix) {
                return false;
            }
        }

        $pos = strpos($wordLower, $matchLower);
        if ($pos !== false) {
            $remainder = substr($wordLower, 0, $pos) . substr($wordLower, $pos + strlen($matchLower));
            foreach ($profanityExpressions as $profanity => $_) {
                if (strlen($profanity) >= 3 && stripos($remainder, $profanity) !== false) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function collapse(string $text): string
    {
        return preg_replace('/(.)\1+/', '$1', strtolower($text)) ?? $text;
    }
}
