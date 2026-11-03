<?php

namespace Blaspsoft\Blasp\Core\Normalizers;

/**
 * Maps character positions in transformed (normalized) text back to the original text.
 *
 * Normalizers can change the length of the text (German ä → ae, sch → sh, Spanish
 * ll → y, collapsed whitespace), so a match found in the normalized text cannot be
 * cut out of the original at the same position.
 */
class OffsetMap
{
    // Above this length a word's offsets are approximated character by character,
    // to avoid normalizing every prefix of a very long token
    private const MAX_PREFIX_SCAN = 256;

    /**
     * @param  array<int, int>  $boundaries  normalized length of the first k original characters, for k = 0..n
     * @param  array<int, bool>  $whitespace  original character indexes that are whitespace
     */
    private function __construct(
        private readonly array $boundaries,
        private readonly array $whitespace = [],
    ) {}

    /**
     * Build the map for $text, where $transform turns original text into the text that
     * was matched against. The transform is applied to whitespace-separated chunks, which
     * holds for normalizers that work within words.
     */
    public static function build(string $text, callable $transform): self
    {
        $chunks = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $boundaries = [0];
        $whitespace = [];
        $offset = 0;

        foreach ($chunks as $chunk) {
            $isWhitespace = preg_match('/^\s+$/', $chunk) === 1;
            foreach (self::chunkBoundaries($chunk, $transform) as $length) {
                if ($isWhitespace) {
                    $whitespace[count($boundaries) - 1] = true;
                }
                $boundaries[] = $offset + $length;
            }
            $offset = end($boundaries);
        }

        // If chunking does not reproduce the transformed text, positions cannot be trusted
        if ($offset !== mb_strlen($transform($text), 'UTF-8')) {
            return self::identity(mb_strlen($text, 'UTF-8'));
        }

        return new self($boundaries, $whitespace);
    }

    public static function identity(int $length): self
    {
        return new self(range(0, $length));
    }

    /**
     * Translate a [start, length] range in the transformed text to the original text.
     *
     * @return array{0: int, 1: int}
     */
    public function toOriginal(int $start, int $length): array
    {
        $count = count($this->boundaries) - 1;

        // Boundaries never decrease, so both ends are found by binary search; a linear scan
        // per match made text with thousands of matches quadratic.

        // Start: the last original character that begins at or before $start
        $low = 0;
        $high = $count;
        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);
            if ($this->boundaries[$mid] <= $start) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }
        $originalStart = $low;

        // End: the first original character that reaches the end of the match, extended over
        // characters that add nothing to the normalized text (the "c" and "h" in sch → sh)
        $end = $start + $length;
        $low = $originalStart;
        $high = $count;
        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            if ($this->boundaries[$mid] >= $end) {
                $high = $mid;
            } else {
                $low = $mid + 1;
            }
        }
        $originalEnd = $low;
        while ($originalEnd < $count && $this->boundaries[$originalEnd + 1] === $end
            && !isset($this->whitespace[$originalEnd])) {
            $originalEnd++;
        }

        return [$originalStart, max(0, $originalEnd - $originalStart)];
    }

    /**
     * @return array<int, int>  normalized length after each character of the chunk
     */
    private static function chunkBoundaries(string $chunk, callable $transform): array
    {
        $chars = mb_str_split($chunk, 1, 'UTF-8');
        $count = count($chars);

        // Only an unchanged chunk maps 1:1; a matching length alone can hide changes that
        // cancel out (German ä → ae plus sch → sh)
        if ($transform($chunk) === $chunk) {
            return range(1, $count);
        }

        $lengths = [];
        $max = 0;

        // Normalizers rewrite within runs of letters, so a long chunk is split at the
        // characters between them and each run is mapped on its own
        if ($count > self::MAX_PREFIX_SCAN) {
            $pieces = preg_split('/([^\p{L}\p{M}]+)/u', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($pieces) > 1) {
                foreach ($pieces as $piece) {
                    foreach (self::chunkBoundaries($piece, $transform) as $length) {
                        $lengths[] = $max + $length;
                    }
                    $max = end($lengths);
                }

                if ($max === mb_strlen($transform($chunk), 'UTF-8')) {
                    return $lengths;
                }

                $lengths = [];
                $max = 0;
            }
        }

        if ($count <= self::MAX_PREFIX_SCAN) {
            $prefix = '';
            foreach ($chars as $char) {
                $prefix .= $char;
                $max = max($max, mb_strlen($transform($prefix), 'UTF-8'));
                $lengths[] = $max;
            }
        } else {
            foreach ($chars as $char) {
                $max += mb_strlen($transform($char), 'UTF-8');
                $lengths[] = $max;
            }
        }

        // Keep the chunk's total exact so later chunks line up
        $lengths[$count - 1] = mb_strlen($transform($chunk), 'UTF-8');

        return $lengths;
    }
}
