<?php

namespace Blaspsoft\Blasp\Drivers;

use Blaspsoft\Blasp\Core\Contracts\DriverInterface;
use Blaspsoft\Blasp\Core\Contracts\MaskStrategyInterface;
use Blaspsoft\Blasp\Core\Dictionary;
use Blaspsoft\Blasp\Core\MatchedWord;
use Blaspsoft\Blasp\Core\Result;
use Blaspsoft\Blasp\Core\Score;
use Blaspsoft\Blasp\Core\Matchers\FalsePositiveFilter;
use Blaspsoft\Blasp\Core\Matchers\CompoundWordDetector;
use Blaspsoft\Blasp\Enums\Severity;

class RegexDriver implements DriverInterface
{
    private FalsePositiveFilter $filter;
    private CompoundWordDetector $compoundDetector;

    public function detect(string $text, Dictionary $dictionary, MaskStrategyInterface $mask, array $options = []): Result
    {
        if (empty($text)) {
            return new Result($text ?? '', $text ?? '', [], 0);
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        // Strip invisible Unicode format characters (zero-width spaces, invisible separators, etc.)
        $text = preg_replace('/\p{Cf}/u', '', $text);

        $this->filter = new FalsePositiveFilter($dictionary->getFalsePositives());
        $this->compoundDetector = new CompoundWordDetector();

        $profanityExpressions = $dictionary->getProfanityExpressions();

        // Sort by key length descending (longest profanity first)
        uksort($profanityExpressions, fn($a, $b) => strlen($b) - strlen($a));

        $normalizer = $dictionary->getNormalizer();
        $normalizedString = $normalizer->normalize($text);
        $originalNormalized = preg_replace('/\s+/', ' ', $normalizedString);

        // Immutable copy for position lookups — never mutated
        $immutableNormalized = $originalNormalized;

        $matchedWords = [];
        $uniqueMap = [];
        $profanitiesCount = 0;
        $continue = true;

        // Track masked character ranges so we don't re-match them
        $maskedRanges = [];

        while ($continue) {
            $continue = false;
            $normalizedString = preg_replace('/\s+/', ' ', $normalizedString);

            foreach ($profanityExpressions as $profanity => $expression) {
                preg_match_all($expression, $normalizedString, $matches, PREG_OFFSET_CAPTURE);

                if (!empty($matches[0])) {
                    foreach ($matches[0] as $match) {
                        $byteStart = $match[1];
                        $byteLength = strlen($match[0]);
                        $start = mb_strlen(substr($normalizedString, 0, $byteStart), 'UTF-8');
                        $length = mb_strlen($match[0], 'UTF-8');
                        $matchedText = $match[0];

                        // Skip if this range overlaps with an already-masked range
                        $matchEnd = $start + $length;
                        $alreadyMasked = false;
                        foreach ($maskedRanges as [$mStart, $mEnd]) {
                            if ($start < $mEnd && $matchEnd > $mStart) {
                                $alreadyMasked = true;
                                break;
                            }
                        }
                        if ($alreadyMasked) {
                            continue;
                        }

                        // Check word boundary spanning (filter uses byte-level operations)
                        if ($this->filter->isSpanningWordBoundary($matchedText, $normalizedString, $byteStart)) {
                            continue;
                        }

                        // Check hex/UUID token (filter uses byte-level operations)
                        if ($this->filter->isInsideHexToken($normalizedString, $byteStart, $byteLength)) {
                            continue;
                        }

                        // Full word context for false positive check (filter uses byte-level operations)
                        $fullWord = $this->filter->getFullWordContext($normalizedString, $byteStart, $byteLength);

                        // Check pure alpha substring against original (unmasked) normalized
                        $originalFullWord = $this->filter->getFullWordContext($immutableNormalized, $byteStart, $byteLength);
                        if ($this->compoundDetector->isPureAlphaSubstring($matchedText, $originalFullWord, $profanity, $profanityExpressions)) {
                            continue;
                        }

                        // False positive check
                        if ($this->filter->isFalsePositive($fullWord)) {
                            continue;
                        }

                        $continue = true;

                        // Mask in normalizedString only (needed for loop termination)
                        // Use SOH control char internally to avoid re-matching when '*' is
                        // a valid substitution character in profanity patterns
                        $normalizedString = mb_substr($normalizedString, 0, $start) . str_repeat("\x01", $length) .
                            mb_substr($normalizedString, $start + $length);

                        // Record masked range using character positions from immutable string
                        $maskedRanges[] = [$start, $matchEnd];

                        // Track match — use position derived from immutable normalized string
                        $profanitiesCount++;

                        // Get the original text at this position from the original input
                        $originalMatchText = mb_substr($text, $start, $length);

                        $matchedWords[] = new MatchedWord(
                            text: $originalMatchText,
                            base: $profanity,
                            severity: $dictionary->getSeverity($profanity),
                            position: $start,
                            length: $length,
                            language: $dictionary->getLanguage(),
                        );

                        if (!isset($uniqueMap[$profanity])) {
                            $uniqueMap[$profanity] = true;
                        }
                    }
                }
            }
        }

        // Apply severity filter before masking so low-severity matches don't suppress overlapping ones
        $minimumSeverity = $options['severity'] ?? null;
        if ($minimumSeverity instanceof Severity) {
            $matchedWords = array_values(array_filter(
                $matchedWords,
                fn(MatchedWord $w) => $w->severity->isAtLeast($minimumSeverity)
            ));
        }

        // Rebuild cleanText from surviving matches (right-to-left)
        $workingCleanString = $text;
        $sorted = $matchedWords;
        usort($sorted, fn($a, $b) => $b->position - $a->position);
        foreach ($sorted as $word) {
            $replacement = $mask->mask($word->text, $word->length);
            $workingCleanString = mb_substr($workingCleanString, 0, $word->position)
                . $replacement
                . mb_substr($workingCleanString, $word->position + $word->length);
        }

        $totalWords = max(1, count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY)));
        $scoreValue = Score::calculate($matchedWords, $totalWords);

        return new Result($text, $workingCleanString, $matchedWords, $scoreValue);
    }
}
