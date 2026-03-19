<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;

class DutchLanguageDetectionTest extends TestCase
{
    public function test_detects_basic_dutch_profanity()
    {
        $result = Blasp::dutch()->check('wat een kut dag');
        $this->assertTrue($result->isOffensive());
    }

    public function test_detects_multiple_dutch_profanities()
    {
        $result = Blasp::dutch()->check('godverdomme wat een klootzak');
        $this->assertTrue($result->isOffensive());
        $this->assertGreaterThanOrEqual(2, $result->count());
    }

    public function test_clean_text_is_not_offensive()
    {
        $result = Blasp::dutch()->check('Goedemorgen, hoe gaat het met jou?');
        $this->assertFalse($result->isOffensive());
    }

    public function test_censors_dutch_profanity()
    {
        $result = Blasp::dutch()->check('wat een kut dag');
        $this->assertStringContainsString('*', $result->clean());
        $this->assertStringNotContainsString('kut', strtolower($result->clean()));
    }

    public function test_detects_mild_severity()
    {
        $result = Blasp::dutch()->check('wat een stomme fout');
        $this->assertTrue($result->isOffensive());
    }

    public function test_detects_moderate_severity()
    {
        $result = Blasp::dutch()->check('jij bent een eikel');
        $this->assertTrue($result->isOffensive());
    }

    public function test_detects_high_severity()
    {
        $result = Blasp::dutch()->check('dit is echt kut');
        $this->assertTrue($result->isOffensive());
    }

    public function test_detects_disease_curses()
    {
        $result = Blasp::dutch()->check('tering wat een dag');
        $this->assertTrue($result->isOffensive());

        $result = Blasp::dutch()->check('tyfus wat een rotdag');
        $this->assertTrue($result->isOffensive());
    }

    public function test_detects_case_insensitive()
    {
        $variants = ['KUT', 'Kut', 'kut', 'KuT'];

        foreach ($variants as $variant) {
            $result = Blasp::dutch()->check("wat een $variant dag");
            $this->assertTrue($result->isOffensive(), "Failed to detect '$variant'");
        }
    }

    public function test_detects_profanity_with_diacritics()
    {
        // Obfuscation using diacritics should still be detected
        $result = Blasp::dutch()->check('wat een küt dag');
        $this->assertTrue($result->isOffensive(), 'Failed to detect diacritic obfuscation of kut');
    }

    public function test_false_positive_zakelijk()
    {
        $result = Blasp::dutch()->check('Dit is een zakelijk gesprek');
        $this->assertFalse($result->isOffensive());
    }

    public function test_false_positive_document()
    {
        $result = Blasp::dutch()->check('Heb je het document al gelezen?');
        $this->assertFalse($result->isOffensive());
    }

    public function test_false_positive_domein()
    {
        $result = Blasp::dutch()->check('Het domein is geregistreerd');
        $this->assertFalse($result->isOffensive());
    }

    public function test_false_positive_pistool()
    {
        $result = Blasp::dutch()->check('De politie droeg een pistool');
        $this->assertFalse($result->isOffensive());
    }

    public function test_false_positive_rots()
    {
        $result = Blasp::dutch()->check('We klommen op de rots');
        $this->assertFalse($result->isOffensive());
    }

    public function test_sentence_with_mixed_clean_and_profane_words()
    {
        $result = Blasp::dutch()->check('Ik heb een kut dag gehad op het werk');
        $this->assertTrue($result->isOffensive());
        $clean = $result->clean();
        $this->assertStringContainsString('dag', $clean);
        $this->assertStringContainsString('werk', $clean);
        $this->assertStringNotContainsString('kut', strtolower($clean));
    }

    public function test_empty_string_is_not_offensive()
    {
        $result = Blasp::dutch()->check('');
        $this->assertFalse($result->isOffensive());
    }

    public function test_dutch_language_available()
    {
        $languages = \Blaspsoft\Blasp\Core\Dictionary::getAvailableLanguages();
        $this->assertContains('dutch', $languages);
    }

    public function test_in_dutch_is_equivalent_to_dutch_shorthand()
    {
        $text = 'wat een kut dag';
        $result1 = Blasp::in('dutch')->check($text);
        $result2 = Blasp::dutch()->check($text);

        $this->assertEquals($result1->isOffensive(), $result2->isOffensive());
        $this->assertEquals($result1->count(), $result2->count());
    }
}
