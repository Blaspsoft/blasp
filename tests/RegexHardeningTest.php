<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Core\Matchers\SafeRegex;
use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;

class RegexHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('blasp.cache.enabled', false);
    }

    /**
     * Inputs that used to backtrack for seconds (or minutes) on a few KB of text.
     * The bound is generous so slow CI machines do not flake; before the fix
     * each of these took several seconds at 2 KB.
     */
    public static function craftedInputs(): array
    {
        return [
            'run of one letter' => [str_repeat('a', 10000) . ' fuck'],
            'run of one letter, no match' => [str_repeat('s', 10000)],
            'run of a letter substitute' => [str_repeat('!', 10000) . ' you lickme'],
            'alternating letter and wildcard' => [str_repeat('o*', 2500)],
            'alternating letter and wildcard 2' => [str_repeat('a*', 2500)],
        ];
    }

    #[DataProvider('craftedInputs')]
    public function test_crafted_input_is_checked_quickly(string $text)
    {
        Blasp::check('warm up');

        $start = microtime(true);
        Blasp::check($text);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(3.0, $elapsed, sprintf('Check took %.2fs', $elapsed));
    }

    public function test_word_after_long_run_is_still_detected()
    {
        // Used to fail open: PCRE gave up on the long run and the driver treated it as clean
        $result = Blasp::check(str_repeat('!', 2000) . ' you lickme');

        $this->assertTrue($result->isOffensive());
        $this->assertStringEndsWith(' you ******', $result->clean());
    }

    public function test_stretched_letter_is_detected_and_fully_masked()
    {
        $result = Blasp::check('what the fuuuuuuuuuuuuuuuuuuuuck');

        $this->assertTrue($result->isOffensive());
        $this->assertSame('what the ' . str_repeat('*', 23), $result->clean());
    }

    public function test_stretched_last_letter_is_fully_masked()
    {
        $result = Blasp::check('you are fuckkkkkkkkkkkkkk');

        $this->assertSame('you are ' . str_repeat('*', 17), $result->clean());
        $this->assertSame('fuckkkkkkkkkkkkkk', $result->words()->first()->text);
    }

    public function test_long_runs_in_clean_text_are_kept()
    {
        $text = 'Sooooooooooooo good!!!!!!!!!!!!!!';

        $this->assertSame($text, Blasp::check($text)->clean());
    }

    public function test_safe_regex_falls_back_to_windows_when_pcre_gives_up()
    {
        $pattern = '/(?:a+)+b|fuck/';
        $subject = str_repeat('a', 40) . 'c and fuck';
        $previous = ini_get('pcre.backtrack_limit');
        $previousJit = ini_get('pcre.jit');

        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '10000');

        try {
            $this->assertFalse(@preg_match_all($pattern, $subject, $m), 'The pattern should exhaust the limit on the whole text');
            $this->assertSame([['fuck', 46]], SafeRegex::matchAll($pattern, $subject));
        } finally {
            ini_set('pcre.backtrack_limit', $previous);
            ini_set('pcre.jit', $previousJit);
        }
    }
}
