<?php

namespace Blaspsoft\Blasp\Core\Masking;

/**
 * Applies replacements to text in a single pass.
 *
 * Rebuilding the clean text with mb_substr per match rescanned the whole string each
 * time, which made dense input (thousands of matches) quadratic.
 */
class TextMasker
{
    /**
     * @param  array<int, array{0: int, 1: int, 2: string}>  $replacements  [character start, character length, replacement], non-overlapping
     */
    public static function apply(string $text, array $replacements): string
    {
        if ($replacements === []) {
            return $text;
        }

        // Overlapping matches (rare; e.g. two matches sharing a character that normalizing
        // expanded) keep the original right-to-left behaviour exactly
        if (self::overlaps($replacements)) {
            return self::applySequentially($text, $replacements);
        }

        usort($replacements, fn ($a, $b) => $a[0] <=> $b[0]);

        // Byte offset of every character position, including the end of the text
        $byteOffsets = [];
        $offset = 0;
        foreach (mb_str_split($text, 1, 'UTF-8') as $index => $char) {
            $byteOffsets[$index] = $offset;
            $offset += strlen($char);
        }
        $last = count($byteOffsets);
        $byteOffsets[$last] = $offset;

        $output = '';
        $cursor = 0;

        foreach ($replacements as [$start, $length, $replacement]) {
            $startByte = $byteOffsets[min(max($start, 0), $last)];
            $endByte = $byteOffsets[min(max($start + $length, 0), $last)];

            $output .= substr($text, $cursor, $startByte - $cursor) . $replacement;
            $cursor = $endByte;
        }

        return $output . substr($text, $cursor);
    }

    /**
     * @param  array<int, array{0: int, 1: int, 2: string}>  $replacements
     */
    private static function overlaps(array $replacements): bool
    {
        usort($replacements, fn ($a, $b) => $a[0] <=> $b[0]);

        $end = PHP_INT_MIN;
        foreach ($replacements as [$start, $length]) {
            if ($start < $end) {
                return true;
            }
            $end = max($end, $start + $length);
        }

        return false;
    }

    /**
     * The previous algorithm: apply each replacement right-to-left with mb_substr.
     *
     * @param  array<int, array{0: int, 1: int, 2: string}>  $replacements
     */
    private static function applySequentially(string $text, array $replacements): string
    {
        usort($replacements, fn ($a, $b) => $b[0] <=> $a[0]);

        foreach ($replacements as [$start, $length, $replacement]) {
            $text = mb_substr($text, 0, $start, 'UTF-8')
                . $replacement
                . mb_substr($text, $start + $length, null, 'UTF-8');
        }

        return $text;
    }
}
