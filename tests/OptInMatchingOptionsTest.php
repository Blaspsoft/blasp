<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Core\Dictionary;
use Blaspsoft\Blasp\Core\Normalizers\EnglishNormalizer;
use Blaspsoft\Blasp\Core\Normalizers\GermanNormalizer;
use Blaspsoft\Blasp\Core\Normalizers\HomoglyphNormalizer;
use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;

/**
 * Matching options that change results for existing text, so each is off by default.
 */
class OptInMatchingOptionsTest extends TestCase
{
    // --- homoglyphs -------------------------------------------------------

    public function test_homoglyph_folding_is_off_by_default(): void
    {
        $this->assertFalse(config('blasp.homoglyphs'));
        $this->assertInstanceOf(EnglishNormalizer::class, \Blaspsoft\Blasp\Core\Dictionary::forLanguage('english')->getNormalizer());
    }

    public function test_cyrillic_and_greek_lookalikes_are_matched_when_enabled(): void
    {
        Config::set('blasp.homoglyphs', true);

        // Cyrillic с and Greek υ
        $this->assertTrue(Blasp::check('fuсk')->isOffensive());
        $this->assertTrue(Blasp::check('fυck')->isOffensive());
    }

    public function test_fullwidth_letters_are_matched_when_enabled(): void
    {
        Config::set('blasp.homoglyphs', true);

        $this->assertTrue(Blasp::check('ｆｕｃｋ')->isOffensive());
    }

    public function test_accented_letters_missing_from_the_substitution_table_are_matched_when_enabled(): void
    {
        if (!class_exists(\Normalizer::class)) {
            $this->markTestSkipped('Accent folding needs ext-intl or symfony/polyfill-intl-normalizer');
        }

        $this->assertNotContains('ā', config('blasp.substitutions')['/a/']);

        Config::set('blasp.homoglyphs', true);

        $this->assertTrue(Blasp::check('dāmn')->isOffensive());
    }

    public function test_the_original_text_is_masked_not_the_folded_form(): void
    {
        Config::set('blasp.homoglyphs', true);

        $result = Blasp::check('Café fuсk');

        $this->assertSame('Café ****', $result->clean());
        $this->assertSame('fuсk', $result->words()->first()->text);
    }

    public function test_language_normalizer_runs_before_folding(): void
    {
        $normalizer = new HomoglyphNormalizer(new GermanNormalizer());

        // ä/ö/ü → ae/oe/ue must still happen, rather than being folded to a/o/u first
        $this->assertSame('Muell', $normalizer->normalize('Müll'));
        $this->assertSame('bloed', $normalizer->normalize('blöd'));
    }

    public function test_german_umlaut_words_are_still_detected_when_enabled(): void
    {
        Config::set('blasp.homoglyphs', true);

        $this->assertTrue(Blasp::german()->check('du bist blöd')->isOffensive());
        $this->assertTrue(Blasp::german()->check('du bist bloed')->isOffensive());
    }

    public function test_folding_preserves_length_and_leaves_other_scripts_alone(): void
    {
        foreach (['fuсk', 'ｆｕｃｋ', 'gānjā', 'Café', 'ﬁne', 'straße', 'मादरचोद', 'こんにちは'] as $input) {
            $this->assertSame(mb_strlen($input), mb_strlen(HomoglyphNormalizer::fold($input)), $input);
        }

        $this->assertSame('मादरचोद', HomoglyphNormalizer::fold('मादरचोद'));
        $this->assertSame('ﬁne', HomoglyphNormalizer::fold('ﬁne'));
        if (class_exists(\Normalizer::class)) {
            $this->assertSame('ganja', HomoglyphNormalizer::fold('gānjā'));
        }
    }

    // --- extended substitutions ---------------------------------------------

    public function test_extended_substitutions_are_off_by_default(): void
    {
        $this->assertFalse(Blasp::check('s1ut')->isOffensive());
    }

    public function test_extended_substitutions_cover_common_digit_leetspeak_when_enabled(): void
    {
        Config::set('blasp.extended_substitutions', true);

        foreach (['s1ut', 'cun7', 'sh17', 'j122'] as $text) {
            $this->assertTrue(Blasp::check($text)->isOffensive(), $text);
        }
    }

    // --- flexible phrases ---------------------------------------------------

    public function test_phrases_match_literally_by_default(): void
    {
        $check = Blasp::block('sieg heil');

        $this->assertTrue($check->check('sieg heil')->isOffensive());
        $this->assertFalse($check->check('siegheil')->isOffensive());
    }

    public function test_a_phrase_entry_matches_across_any_separator_when_enabled(): void
    {
        Config::set('blasp.flexible_phrases', true);

        $check = Blasp::block('sieg heil');

        foreach (['sieg heil', 'sieg-heil', 'siegheil', 'sieg_heil', 's-i-e-g heil'] as $text) {
            $this->assertTrue($check->check($text)->isOffensive(), $text);
        }

        $this->assertSame('*********', $check->check('sieg heil')->clean());
        $this->assertSame('********', $check->check('siegheil')->clean());
    }

    public function test_shipped_phrase_entries_match_hyphenated_when_enabled(): void
    {
        Config::set('blasp.flexible_phrases', true);

        $this->assertTrue(Blasp::check('blow-job')->isOffensive());
        $this->assertTrue(Blasp::check('blowjob')->isOffensive());
    }

    public function test_a_space_after_a_letter_does_not_add_a_second_separator_slot(): void
    {
        Config::set('blasp.flexible_phrases', true);

        // The letter before the space already ends in a separator slot, so the space adds
        // none: the phrase compiles to as many slots as the joined word. Devanagari letters
        // have no substitution entry, which is the case that went wrong.
        foreach ([['sieg heil', 'siegheil'], ['गांड मरा', 'गांडमरा']] as [$phrase, $joined]) {
            $expressions = Dictionary::forLanguage('english', ['block' => [$phrase, $joined]])->getProfanityExpressions();

            $this->assertSame(
                substr_count($expressions[$joined], '{0,3}?'),
                substr_count($expressions[$phrase], '{0,3}?'),
                $phrase
            );
        }

        $this->assertTrue(Blasp::block('sieg heil')->check('sieg-heil')->isOffensive());
    }

    public function test_flexible_phrases_check_long_runs_quickly(): void
    {
        Config::set('blasp.flexible_phrases', true);

        $check = Blasp::block('sieg heil');
        $check->check('warm up');

        $start = microtime(true);
        $check->check(str_repeat('s', 2000));
        $check->check(str_repeat('s ', 2000));
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(3.0, $elapsed, sprintf('Check took %.2fs', $elapsed));
    }

    // --- cache --------------------------------------------------------------

    public function test_toggling_an_option_invalidates_cached_results(): void
    {
        Config::set('blasp.cache.results', true);

        $this->assertFalse(Blasp::check('fuсk')->isOffensive());

        Config::set('blasp.homoglyphs', true);

        $this->assertTrue(Blasp::check('fuсk')->isOffensive());
    }
}
