<?php

namespace Blaspsoft\Blasp\Core;

use Blaspsoft\Blasp\Core\Contracts\DriverInterface;
use Blaspsoft\Blasp\Core\Contracts\MaskStrategyInterface;
use Blaspsoft\Blasp\Core\Masking\CharacterMask;

class Analyzer
{
    public function analyze(
        string $text,
        DriverInterface $driver,
        Dictionary $dictionary,
        ?MaskStrategyInterface $mask = null,
        array $options = [],
    ): Result {
        $mask = $mask ?? new CharacterMask(config('blasp.mask', config('blasp.mask_character', '*')));

        // Invisible Unicode format characters (zero-width spaces, invisible separators, etc.)
        // are stripped before any driver sees the text, ensuring consistent positions across
        // pipeline drivers. preg_match returns false on malformed UTF-8, which drivers handle.
        if (preg_match('/\p{Cf}/u', $text) !== 1) {
            return $driver->detect($text, $dictionary, $mask, $options);
        }

        // Map each character of the stripped text back to its position in the original
        $chars = mb_str_split($text, 1, 'UTF-8');
        $stripped = '';
        $positionMap = [];
        foreach ($chars as $i => $char) {
            if (preg_match('/^\p{Cf}$/u', $char) !== 1) {
                $stripped .= $char;
                $positionMap[] = $i;
            }
        }

        $result = $driver->detect($stripped, $dictionary, $mask, $options);

        return $this->restoreFormatCharacters($text, $result, $positionMap, $mask);
    }

    /**
     * Rebuild the result against the original text so format characters outside a match
     * (e.g. the zero-width joiners in emoji sequences like 💆‍♀️) are kept. Any inside a
     * match are masked away along with the profanity.
     */
    private function restoreFormatCharacters(string $text, Result $result, array $positionMap, MaskStrategyInterface $mask): Result
    {
        $matchedWords = [];
        $replacements = [];

        foreach ($result->words() as $word) {
            $start = $positionMap[$word->position];
            $end = $positionMap[$word->position + $word->length - 1] + 1;

            $matchedWords[] = new MatchedWord(
                text: mb_substr($text, $start, $end - $start, 'UTF-8'),
                base: $word->base,
                severity: $word->severity,
                position: $start,
                length: $end - $start,
                language: $word->language,
            );

            $replacements[] = [$start, $end, $mask->mask($word->text, $word->length)];
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
