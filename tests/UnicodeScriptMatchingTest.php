<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Core\Matchers\FalsePositiveFilter;
use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;

class UnicodeScriptMatchingTest extends TestCase
{
    public function test_regex_driver_matches_word_ending_in_a_combining_mark()
    {
        // "चूतिया" ends in the vowel sign "ा" (\p{M})
        $result = Blasp::block('चूतिया')->check('तू एक चूतिया है');

        $this->assertTrue($result->isOffensive());
        $this->assertStringNotContainsString('चूतिया', $result->clean());
    }

    public function test_regex_driver_tolerates_separators_inside_non_latin_words()
    {
        $result = Blasp::block('मादरचोद')->check('साले मा-दर-चोद');

        $this->assertTrue($result->isOffensive());
    }

    public function test_pattern_driver_matches_word_ending_in_a_combining_mark()
    {
        $result = Blasp::driver('pattern')->block('चूतिया')->check('तू एक चूतिया है');

        $this->assertTrue($result->isOffensive());
    }

    public function test_pattern_driver_still_respects_word_boundaries()
    {
        $result = Blasp::driver('pattern')->block('चूतिया')->check('तू एक चूतियाजी है');

        $this->assertFalse($result->isOffensive());
    }

    public function test_full_word_context_extends_across_multibyte_characters()
    {
        $filter = new FalsePositiveFilter([]);
        $text = 'एक चूतियापन है';
        $start = strpos($text, 'चूतिया');

        $this->assertSame('चूतियापन', $filter->getFullWordContext($text, $start, strlen('चूतिया')));
    }

    public function test_false_positive_containing_combining_marks_is_honoured()
    {
        Config::set('blasp.false_positives', ['गांडीव']);

        $result = Blasp::block('गांड')->check('अर्जुन का गांडीव');

        $this->assertFalse($result->isOffensive());
    }

    public function test_latin_matching_is_unchanged()
    {
        $this->assertTrue(Blasp::check('f-u-c-k')->isOffensive());
        $this->assertTrue(Blasp::driver('pattern')->check('what the fuck')->isOffensive());
        $this->assertFalse(Blasp::check('scunthorpe')->isOffensive());
    }
}
