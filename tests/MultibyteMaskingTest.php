<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;

class MultibyteMaskingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('blasp.cache.enabled', false);
    }

    public function test_repeated_accented_words_are_all_masked()
    {
        $this->assertSame('**** ****', Blasp::check('fück fück')->clean());
        $this->assertSame('**** **** ****', Blasp::check('fück fück fück')->clean());
    }

    public function test_repeated_accented_words_report_correct_positions()
    {
        $words = Blasp::check('fück fück')->words();

        $this->assertSame([['fück', 0, 4], ['fück', 5, 4]], $words->map(fn ($w) => [$w->text, $w->position, $w->length])->all());
    }

    public function test_words_after_accented_matches_are_still_detected()
    {
        $result = Blasp::check('fück shït cünt dïck twät abcdefgh ass');

        $this->assertStringEndsWith(' abcdefgh ***', $result->clean());
    }

    public function test_repeated_accented_words_in_all_languages()
    {
        $this->assertSame('****** ******', Blasp::inAllLanguages()->check('cabrón cabrón')->clean());
    }

    public function test_accented_match_does_not_spill_into_next_word()
    {
        $this->assertSame('**** and ****', Blasp::check('fück and shit')->clean());

        // Accented text behaves like its ASCII spelling
        $this->assertSame(Blasp::check('fuck a b')->clean(), Blasp::check('fück a b')->clean());
    }

    public function test_pattern_driver_handles_text_whose_lowercase_changes_length()
    {
        $this->assertSame('İİİ ****', Blasp::driver('pattern')->check('İİİ shit')->clean());
        $this->assertSame('İ **** you', Blasp::driver('pattern')->check('İ fuck you')->clean());
    }
}
