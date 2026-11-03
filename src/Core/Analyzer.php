<?php

namespace Blaspsoft\Blasp\Core;

use Blaspsoft\Blasp\Core\Contracts\DriverInterface;
use Blaspsoft\Blasp\Core\Contracts\MaskStrategyInterface;
use Blaspsoft\Blasp\Core\Masking\CharacterMask;

class Analyzer
{
    // Runs of one character longer than this are shortened before matching. Nothing in
    // a word needs more repeats ("fuuuuuuuck" still matches), and long runs ("!!!!…",
    // "aaaa…") are what made the expressions backtrack for seconds.
    public const MAX_RUN = 8;

    public function analyze(
        string $text,
        DriverInterface $driver,
        Dictionary $dictionary,
        ?MaskStrategyInterface $mask = null,
        array $options = [],
    ): Result {
        $mask = $mask ?? new CharacterMask(config('blasp.mask', config('blasp.mask_character', '*')));

        // Invisible Unicode format characters (zero-width spaces, invisible separators, etc.)
        // are removed before any driver sees the text, ensuring consistent positions across
        // pipeline drivers, and long runs of one character are shortened. preg_match returns
        // false on malformed UTF-8, which drivers handle.
        if (preg_match('/\p{Cf}|(.)\1{' . self::MAX_RUN . '}/u', $text) !== 1) {
            return $driver->detect($text, $dictionary, $mask, $options);
        }

        // Map each character of the reduced text back to its position in the original
        $chars = mb_str_split($text, 1, 'UTF-8');
        $reduced = '';
        $positionMap = [];
        $shortened = [];
        $previous = null;
        $run = 0;
        foreach ($chars as $i => $char) {
            if (preg_match('/^\p{Cf}$/u', $char) === 1) {
                continue;
            }

            $run = $char === $previous ? $run + 1 : 1;
            $previous = $char;

            if ($run > self::MAX_RUN) {
                $shortened[$i] = true;
                continue;
            }

            $reduced .= $char;
            $positionMap[] = $i;
        }

        // The driver's clean text is rebuilt below, so it gets a plain mask; the real one
        // (which may be a callback) is applied once per match during the rebuild
        $result = $driver->detect($reduced, $dictionary, new CharacterMask('*'), $options);

        return $this->restoreOriginal($text, $chars, $result, $positionMap, $shortened, $mask);
    }

    /**
     * Rebuild the result against the original text. Format characters outside a match
     * (e.g. the zero-width joiners in emoji sequences like 💆‍♀️) are kept, and any inside a
     * match are masked away along with the profanity. A match that ends in a shortened run
     * covers the whole run.
     */
    private function restoreOriginal(string $text, array $chars, Result $result, array $positionMap, array $shortened, MaskStrategyInterface $mask): Result
    {
        $matchedWords = [];
        $replacements = [];

        // Driver positions come from normalized text, which can be longer than the input
        // (German ä → ae), so clamp them to the input rather than reading past its end
        $last = count($positionMap) - 1;
        $count = count($chars);

        foreach ($result->words() as $word) {
            $start = $positionMap[min($word->position, $last)];
            $end = $positionMap[min($word->position + $word->length - 1, $last)] + 1;

            while ($end < $count && isset($shortened[$end])) {
                $end++;
            }

            $original = mb_substr($text, $start, $end - $start, 'UTF-8');

            $matchedWords[] = new MatchedWord(
                text: $original,
                base: $word->base,
                severity: $word->severity,
                position: $start,
                length: $end - $start,
                language: $word->language,
            );

            // Mask the original span minus its format characters
            $visible = preg_replace('/\p{Cf}/u', '', $original) ?? $original;
            $replacements[] = [$start, $end, $mask->mask($visible, mb_strlen($visible, 'UTF-8'))];
        }

        // Mask right-to-left so earlier positions stay valid
        usort($replacements, fn ($a, $b) => $b[0] - $a[0]);
        $cleanText = $text;
        foreach ($replacements as [$start, $end, $replacement]) {
            $cleanText = mb_substr($cleanText, 0, $start, 'UTF-8')
                . $replacement
                . mb_substr($cleanText, $end, null, 'UTF-8');
        }

        return new Result($text, $cleanText, $matchedWords, $result->score());
    }
}
