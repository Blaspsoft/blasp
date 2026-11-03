<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Core\Masking\TextMasker;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TextMaskerTest extends BaseTestCase
{
    public function test_applies_replacements_in_one_pass()
    {
        $this->assertSame('a **** b ####', TextMasker::apply('a fuck b shit', [[9, 4, '####'], [2, 4, '****']]));
    }

    public function test_handles_multibyte_text()
    {
        $this->assertSame('ä **** ö ****', TextMasker::apply('ä fück ö shït', [[2, 4, '****'], [9, 4, '****']]));
    }

    public function test_replacement_can_change_length()
    {
        $this->assertSame('a [removed] b', TextMasker::apply('a fuck b', [[2, 4, '[removed]']]));
    }

    public function test_no_replacements_returns_text()
    {
        $this->assertSame('hello', TextMasker::apply('hello', []));
    }

    public function test_overlapping_replacements_match_previous_right_to_left_behaviour()
    {
        $text = 'a*u*s*g*e*l*ö*s*c*h*t';
        $replacements = [[12, 6, '******'], [11, 2, '**']];

        $expected = $text;
        foreach ([[12, 6, '******'], [11, 2, '**']] as [$start, $length, $replacement]) {
            $expected = mb_substr($expected, 0, $start) . $replacement . mb_substr($expected, $start + $length);
        }

        $this->assertSame($expected, TextMasker::apply($text, $replacements));
        $this->assertSame($expected, TextMasker::apply($text, array_reverse($replacements)));
    }
}
