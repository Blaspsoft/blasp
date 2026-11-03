<?php

namespace Blaspsoft\Blasp\Core\Matchers;

/**
 * Runs profanity expressions without failing open.
 *
 * When PCRE gives up on a long text (backtrack or JIT stack limit), preg_match_all
 * returns false, which used to be treated as "no match" and let the whole text pass
 * as clean. The expression is instead re-run over small overlapping windows, so the
 * rest of the text is still checked.
 */
class SafeRegex
{
    private const WINDOW = 128;
    private const MIN_WINDOW = 16;

    /**
     * All matches of $pattern in $subject as [text, byte offset] pairs, like
     * preg_match_all's PREG_OFFSET_CAPTURE output for the whole match.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    public static function matchAll(string $pattern, string $subject): array
    {
        if (preg_match_all($pattern, $subject, $matches, PREG_OFFSET_CAPTURE) !== false) {
            return $matches[0];
        }

        return self::matchInWindows($pattern, $subject);
    }

    /**
     * Match $pattern in overlapping windows of $subject. A window the pattern still gives
     * up on is split into smaller windows, so only the crafted run itself goes unchecked.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    private static function matchInWindows(string $pattern, string $subject, int $window = self::WINDOW): array
    {
        $chars = mb_str_split($subject, 1, 'UTF-8');
        $count = count($chars);

        // Byte offset of each character, so window matches map back to the subject
        $byteOffsets = [];
        $offset = 0;
        foreach ($chars as $i => $char) {
            $byteOffsets[$i] = $offset;
            $offset += strlen($char);
        }
        $byteOffsets[$count] = $offset;

        $found = [];
        $step = intdiv($window, 2);

        for ($start = 0; $start < $count; $start += $step) {
            $end = min($start + $window, $count);
            $windowStart = $byteOffsets[$start];
            $windowEnd = $byteOffsets[$end];
            $text = substr($subject, $windowStart, $windowEnd - $windowStart);

            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) !== false) {
                $windowMatches = $matches[0];
            } elseif ($window > self::MIN_WINDOW) {
                $windowMatches = self::matchInWindows($pattern, $text, intdiv($window, 2));
            } else {
                $windowMatches = [];
            }

            foreach ($windowMatches as [$matched, $matchOffset]) {
                $absolute = $windowStart + $matchOffset;
                $absoluteEnd = $absolute + strlen($matched);

                // A match touching a window edge may be cut short; the neighbouring
                // window overlaps it by half and sees it whole
                if (($start > 0 && $absolute === $windowStart) || ($end < $count && $absoluteEnd === $windowEnd)) {
                    continue;
                }

                $found[$absolute] ??= [$matched, $absolute];
            }

            if ($end === $count) {
                break;
            }
        }

        ksort($found);

        return array_values($found);
    }
}
