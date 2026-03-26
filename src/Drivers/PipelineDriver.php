<?php

namespace Blaspsoft\Blasp\Drivers;

use Blaspsoft\Blasp\Core\Contracts\DriverInterface;
use Blaspsoft\Blasp\Core\Contracts\MaskStrategyInterface;
use Blaspsoft\Blasp\Core\Dictionary;
use Blaspsoft\Blasp\Core\MatchedWord;
use Blaspsoft\Blasp\Core\Result;
use Blaspsoft\Blasp\Core\Score;

class PipelineDriver implements DriverInterface
{
    /** @param DriverInterface[] $drivers */
    public function __construct(private array $drivers) {}

    public function detect(string $text, Dictionary $dictionary, MaskStrategyInterface $mask, array $options = []): Result
    {
        if (empty($text)) {
            return new Result($text ?? '', $text ?? '', [], 0);
        }

        // 1. Run each sub-driver, collecting all Result objects
        $allMatches = [];
        foreach ($this->drivers as $driver) {
            $result = $driver->detect($text, $dictionary, $mask, $options);
            foreach ($result->words() as $match) {
                $allMatches[] = $match;
            }
        }

        if (empty($allMatches)) {
            return new Result($text, $text, [], 0);
        }

        // 2. Sort by position ascending, then length descending
        usort($allMatches, function (MatchedWord $a, MatchedWord $b) {
            if ($a->position !== $b->position) {
                return $a->position <=> $b->position;
            }
            return $b->length <=> $a->length;
        });

        // 3. Deduplicate overlapping position ranges (greedy, longest-first at each position)
        $kept = [];
        foreach ($allMatches as $match) {
            $overlaps = false;
            foreach ($kept as $existing) {
                $existingEnd = $existing->position + $existing->length;
                $matchEnd = $match->position + $match->length;

                if ($match->position < $existingEnd && $matchEnd > $existing->position) {
                    $overlaps = true;
                    break;
                }
            }

            if (!$overlaps) {
                $kept[] = $match;
            }
        }

        // 4. Build clean text by applying masks right-to-left (preserves positions)
        $cleanText = $text;
        $reversed = array_reverse($kept);
        foreach ($reversed as $match) {
            $replacement = $mask->mask($match->text, $match->length);
            $cleanText = mb_substr($cleanText, 0, $match->position, 'UTF-8') . $replacement . mb_substr($cleanText, $match->position + $match->length, null, 'UTF-8');
        }

        // 5. Recalculate score from merged matches
        $totalWords = max(1, count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY)));
        $scoreValue = Score::calculate($kept, $totalWords);

        return new Result($text, $cleanText, $kept, $scoreValue);
    }
}
