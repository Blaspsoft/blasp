<?php

namespace Blaspsoft\Blasp\Drivers;

use Blaspsoft\Blasp\Core\Contracts\DriverInterface;
use Blaspsoft\Blasp\Core\Contracts\MaskStrategyInterface;
use Blaspsoft\Blasp\Core\Dictionary;
use Blaspsoft\Blasp\Core\MatchedWord;
use Blaspsoft\Blasp\Core\Result;
use Blaspsoft\Blasp\Core\Score;
use Blaspsoft\Blasp\Enums\Severity;

class PatternDriver implements DriverInterface
{
    public function detect(string $text, Dictionary $dictionary, MaskStrategyInterface $mask, array $options = []): Result
    {
        if (empty($text)) {
            return new Result($text ?? '', $text ?? '', [], 0);
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $matchedWords = [];
        $lowerText = mb_strtolower($text, 'UTF-8');
        $profanities = $dictionary->getProfanities();
        $falsePositives = array_map(fn($fp) => mb_strtolower($fp, 'UTF-8'), $dictionary->getFalsePositives());

        // Sort profanities by length descending for longest-match-first
        usort($profanities, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($profanities as $profanity) {
            $lowerProfanity = mb_strtolower($profanity, 'UTF-8');
            $pattern = '/\b' . preg_quote($lowerProfanity, '/') . '\b/iu';

            if (preg_match_all($pattern, $lowerText, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $start = mb_strlen(substr($lowerText, 0, $match[1]), 'UTF-8');
                    $length = mb_strlen($match[0], 'UTF-8');
                    $originalMatch = mb_substr($text, $start, $length);

                    // Skip false positives
                    if (in_array($lowerProfanity, $falsePositives)) {
                        continue;
                    }

                    $matchedWords[] = new MatchedWord(
                        text: $originalMatch,
                        base: $profanity,
                        severity: $dictionary->getSeverity($profanity),
                        position: $start,
                        length: $length,
                        language: $dictionary->getLanguage(),
                    );
                }
            }
        }

        // Apply severity filter before dedup so shorter high-severity matches aren't swallowed
        $minimumSeverity = $options['severity'] ?? null;
        if ($minimumSeverity instanceof Severity) {
            $matchedWords = array_values(array_filter(
                $matchedWords,
                fn(MatchedWord $w) => $w->severity->isAtLeast($minimumSeverity)
            ));
        }

        // Deduplicate overlapping matches (longest-first already recorded)
        usort($matchedWords, fn($a, $b) => $a->position - $b->position ?: $b->length - $a->length);
        $deduplicated = [];
        $coveredEnd = -1;
        foreach ($matchedWords as $mw) {
            if ($mw->position >= $coveredEnd) {
                $deduplicated[] = $mw;
                $coveredEnd = $mw->position + $mw->length;
            }
        }
        $matchedWords = $deduplicated;

        // Rebuild cleanText from surviving matches (right-to-left)
        $cleanText = $text;
        $sorted = $matchedWords;
        usort($sorted, fn($a, $b) => $b->position - $a->position);
        foreach ($sorted as $word) {
            $replacement = $mask->mask($word->text, $word->length);
            $cleanText = mb_substr($cleanText, 0, $word->position)
                . $replacement
                . mb_substr($cleanText, $word->position + $word->length);
        }

        $totalWords = max(1, count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY)));
        $scoreValue = Score::calculate($matchedWords, $totalWords);

        return new Result($text, $cleanText, $matchedWords, $scoreValue);
    }
}
