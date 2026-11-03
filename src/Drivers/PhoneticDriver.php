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
use Blaspsoft\Blasp\Core\Matchers\PhoneticMatcher;
use Blaspsoft\Blasp\Enums\Severity;
use Blaspsoft\Blasp\Core\Normalizers\OffsetMap;

class PhoneticDriver implements DriverInterface
{
    public function __construct(
        private int $phonemes = 4,
        private int $minWordLength = 3,
        private float $maxDistanceRatio = 0.6,
        private array $phoneticFalsePositives = [],
        private array $supportedLanguages = ['english'],
    ) {}

    public function detect(string $text, Dictionary $dictionary, MaskStrategyInterface $mask, array $options = []): Result
    {
        if (empty($text)) {
            return new Result($text ?? '', $text ?? '', [], 0);
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        // Phonetic matching (metaphone) is English-oriented — skip unsupported languages
        $language = $dictionary->getLanguage();
        $languages = array_map('strtolower', explode(',', $language));
        $supported = array_map('strtolower', $this->supportedLanguages);

        $isSupported = false;
        foreach ($languages as $lang) {
            if (in_array(trim($lang), $supported, true)) {
                $isSupported = true;
                break;
            }
        }

        if (!$isSupported) {
            return new Result($text, $text, [], 0);
        }

        $filter = new FalsePositiveFilter($dictionary->getFalsePositives());
        $allowed = array_flip($dictionary->getAllowList());

        $matcher = new PhoneticMatcher(
            profanities: $dictionary->getProfanities(),
            phonemes: $this->phonemes,
            minWordLength: $this->minWordLength,
            maxDistanceRatio: $this->maxDistanceRatio,
            phoneticFalsePositives: $this->phoneticFalsePositives,
        );

        $normalizer = $dictionary->getNormalizer();
        $normalized = $normalizer->normalize($text);
        $offsetMap = OffsetMap::build($text, fn (string $s) => $normalizer->normalize($s));

        // Tokenize
        preg_match_all('/\b[\w\']+\b/u', $normalized, $matches, PREG_OFFSET_CAPTURE);
        $tokens = $matches[0] ?? [];

        $matchedWords = [];

        foreach ($tokens as $token) {
            $word = $token[0];
            $byteStart = $token[1];
            $byteLength = strlen($word);
            $start = mb_strlen(substr($normalized, 0, $byteStart), 'UTF-8');
            $length = mb_strlen($word, 'UTF-8');

            // Skip dictionary false positives
            if ($filter->isFalsePositive($word)) {
                continue;
            }

            // An allowed word stays allowed even if it sounds like another list word
            // (allow('fuck') used to still flag "fuck" as "fucka")
            if (isset($allowed[mb_strtolower($word, 'UTF-8')])) {
                continue;
            }

            // Skip hex/UUID tokens (filter uses byte-level operations)
            if ($filter->isInsideHexToken($normalized, $byteStart, $byteLength)) {
                continue;
            }

            $baseWord = $matcher->match($word);
            if ($baseWord === null) {
                continue;
            }

            [$originalStart, $originalLength] = $offsetMap->toOriginal($start, $length);
            $originalWord = mb_substr($text, $originalStart, $originalLength);

            $matchedWords[] = new MatchedWord(
                text: $originalWord,
                base: $baseWord,
                severity: $dictionary->getSeverity($baseWord),
                position: $originalStart,
                length: $originalLength,
                language: $dictionary->getLanguage(),
            );
        }

        // Apply severity filter
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
        $cleanText = TextMasker::apply($text, $replacements);

        $totalWords = max(1, count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY)));
        $scoreValue = Score::calculate($matchedWords, $totalWords);

        return new Result($text, $cleanText, $matchedWords, $scoreValue);
    }
}
