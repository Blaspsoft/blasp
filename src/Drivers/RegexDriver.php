<?php

namespace Blaspsoft\Blasp\Drivers;

use Blaspsoft\Blasp\Core\Contracts\DriverInterface;
use Blaspsoft\Blasp\Core\Contracts\MaskStrategyInterface;
use Blaspsoft\Blasp\Core\Dictionary;
use Blaspsoft\Blasp\Core\MatchedWord;
use Blaspsoft\Blasp\Core\Masking\TextMasker;
use Blaspsoft\Blasp\Core\Result;
use Blaspsoft\Blasp\Core\Score;
use Blaspsoft\Blasp\Core\Matchers\FalsePositiveFilter;
use Blaspsoft\Blasp\Core\Matchers\CompoundWordDetector;
use Blaspsoft\Blasp\Core\Matchers\SafeRegex;
use Blaspsoft\Blasp\Core\Normalizers\OffsetMap;
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

        $this->filter = new FalsePositiveFilter($dictionary->getFalsePositives());
        $this->compoundDetector = new CompoundWordDetector();

        $matchEmbedded = (bool) ($options['match_embedded'] ?? false);

        // Words the app blocked explicitly are always matched inside longer words
        $blockedWords = array_flip($dictionary->getBlockList());

        $profanityExpressions = $dictionary->getProfanityExpressions();

        // Sort by key length descending (longest profanity first)
        uksort($profanityExpressions, fn($a, $b) => strlen($b) - strlen($a));

        $normalizer = $dictionary->getNormalizer();
        $normalizedString = $normalizer->normalize($text);
        $originalNormalized = preg_replace('/\s+/', ' ', $normalizedString);

        // Immutable copy for position lookups — never mutated
        $immutableNormalized = $originalNormalized;

        // Character index of each character's first byte. Masking keeps byte offsets
        // stable, so match offsets map straight to character positions.
        $charIndexAtByte = [];
        $byteOffset = 0;
        foreach (mb_str_split($immutableNormalized, 1, 'UTF-8') as $index => $char) {
            $charIndexAtByte[$byteOffset] = $index;
            $byteOffset += strlen($char);
        }
        $charIndexAtByte[$byteOffset] = count($charIndexAtByte);

        // Normalizing can change the length of the text, so match positions are mapped back
        $offsetMap = OffsetMap::build($text, fn (string $s) => preg_replace('/\s+/', ' ', $normalizer->normalize($s)));

        $matchedWords = [];
        $uniqueMap = [];
        $profanitiesCount = 0;
        $continue = true;

        // Track masked characters so we don't re-match them
        $maskedChars = [];

        while ($continue) {
            $continue = false;
            $normalizedString = preg_replace('/\s+/', ' ', $normalizedString);

            foreach ($profanityExpressions as $profanity => $expression) {
                // Bounded matching: a crafted input that exhausts the backtrack limit is
                // rescanned in windows rather than treated as clean
                $matches = SafeRegex::matchAll($expression, $normalizedString);

                if (!empty($matches)) {
                    foreach ($matches as $match) {
                        $byteStart = $match[1];
                        $byteLength = strlen($match[0]);
                        $start = $charIndexAtByte[$byteStart];
                        $length = $charIndexAtByte[$byteStart + $byteLength] - $start;
                        $matchedText = $match[0];

                        // Skip if this range overlaps with an already-masked range
                        $matchEnd = $start + $length;
                        $alreadyMasked = false;
                        for ($i = $start; $i < $matchEnd; $i++) {
                            if (isset($maskedChars[$i])) {
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
                        if (!$matchEmbedded && !isset($blockedWords[$profanity]) && $this->compoundDetector->isPureAlphaSubstring($matchedText, $originalFullWord, $profanity, $profanityExpressions)) {
                            continue;
                        }

                        // False positive check
                        if ($this->filter->isFalsePositive($fullWord)) {
                            continue;
                        }

                        $continue = true;

                        // Mask in normalizedString only (needed for loop termination)
                        // Use SOH control char internally to avoid re-matching when '*' is
                        // a valid substitution character in profanity patterns. One per byte,
                        // so the offsets of later matches in this batch stay valid ("fück fück").
                        $normalizedString = substr_replace($normalizedString, str_repeat("\x01", $byteLength), $byteStart, $byteLength);

                        // Record masked characters using positions from immutable string
                        for ($i = $start; $i < $matchEnd; $i++) {
                            $maskedChars[$i] = true;
                        }

                        // Track match — use position derived from immutable normalized string
                        $profanitiesCount++;

                        // Get the original text at this position from the original input
                        [$originalStart, $originalLength] = $offsetMap->toOriginal($start, $length);
                        $originalMatchText = mb_substr($text, $originalStart, $originalLength);

                        $matchedWords[] = new MatchedWord(
                            text: $originalMatchText,
                            base: $profanity,
                            severity: $dictionary->getSeverity($profanity),
                            position: $originalStart,
                            length: $originalLength,
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

        // Rebuild cleanText from surviving matches. Masks are generated right-to-left, as
        // before, so a callback mask sees the same order; the text is rebuilt in one pass.
        $sorted = $matchedWords;
        usort($sorted, fn($a, $b) => $b->position - $a->position);
        $replacements = [];
        foreach ($sorted as $word) {
            $replacements[] = [$word->position, $word->length, $mask->mask($word->text, $word->length)];
        }
        $workingCleanString = TextMasker::apply($text, $replacements);

        $totalWords = max(1, count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY)));
        $scoreValue = Score::calculate($matchedWords, $totalWords);

        return new Result($text, $workingCleanString, $matchedWords, $scoreValue);
    }
}
