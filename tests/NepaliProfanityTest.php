<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Core\Dictionary;
use Blaspsoft\Blasp\Enums\Severity;
use Blaspsoft\Blasp\Facades\Blasp;

class NepaliProfanityTest extends TestCase
{
    public function test_nepali_is_an_available_language()
    {
        $this->assertContains('nepali', Dictionary::getAvailableLanguages());

        $config = Dictionary::loadLanguageConfig('nepali');
        $this->assertNotEmpty($config['profanities']);
        $this->assertArrayHasKey('severity', $config);
    }

    public function test_detects_devanagari_profanities()
    {
        $cases = [
            'मुजी' => 'ए मुजी',
            'माछिक्ने' => 'माछिक्ने मान्छे',
            'रण्डी' => 'त्यो रण्डी',
            'पुती' => 'तेरो पुती',
            'कुकुर' => 'कुकुर जस्तो',
            'गांड' => 'तेरो गांड',
            'बोका' => 'यो बोका',
        ];

        foreach ($cases as $word => $text) {
            $result = Blasp::nepali()->check($text);
            $this->assertTrue($result->isOffensive(), "Failed to detect Nepali: $word");
            $this->assertStringNotContainsString($word, $result->clean(), "$word was not masked");
            $this->assertStringContainsString('*', $result->clean());
        }
    }

    public function test_detects_romanized_profanities()
    {
        $cases = [
            'muji' => 'k gardai xas muji',
            'machikne' => 'machikne manche',
            'randi' => 'tyo randi',
            'puti' => 'tero puti',
            'kukur' => 'kukur jasto',
            'lado' => 'mero lado',
            'chikne' => 'ama chikne',
        ];

        foreach ($cases as $word => $text) {
            $result = Blasp::nepali()->check($text);
            $this->assertTrue($result->isOffensive(), "Failed to detect romanized Nepali: $word");
            $this->assertStringNotContainsString($word, strtolower($result->clean()), "$word was not masked");
        }
    }

    public function test_detects_mixed_script_sentence()
    {
        $result = Blasp::nepali()->check('yo मुजी k ho machikne');

        $this->assertTrue($result->isOffensive());
        $this->assertSame(2, $result->count());
        $this->assertSame('yo **** k ho ********', $result->clean());
    }

    public function test_masking_keeps_correct_positions_in_devanagari()
    {
        $result = Blasp::nepali()->check('त्यो मान्छे मुजी हो');

        $this->assertSame('त्यो मान्छे **** हो', $result->clean());
    }

    public function test_matches_spelling_variants()
    {
        $variants = [
            'मुजि',              // short vowel
            'गाँड',              // chandrabindu
            'रन्डी', 'रंडी',      // alternative nasal spellings
            'मछिक्ने',           // shortened form
            'mujee', 'mooji',     // romanized vowel variants
            'machikney',
            'kukur ko chhoro',
            'MUJI', 'm.u.j.i', 'मु-जी',
        ];

        foreach ($variants as $variant) {
            $this->assertTrue(Blasp::nepali()->check($variant)->isOffensive(), "Failed to detect variant: $variant");
        }
    }

    public function test_false_positives_are_not_flagged()
    {
        $clean = [
            'भारी बोकाउनु पर्छ',   // bokaunu contains बोका
            'मसाला हाल',           // masala contains साला
            'लंडन गएको थिएँ',      // London
            'हामी चिकित्सा गर्छौं', // chikitsa — चिक is intentionally not listed
            'गुरु आउनुभयो',        // guru — गु alone is intentionally not listed
            'putin met the president',
            'the computing course',
            'we went to Turin',
        ];

        foreach ($clean as $text) {
            $result = Blasp::nepali()->check($text);
            $this->assertFalse($result->isOffensive(), "False positive on: $text -> {$result->clean()}");
        }
    }

    public function test_severity_levels()
    {
        $this->assertSame(Severity::Mild, Blasp::nepali()->check('साला')->severity());
        $this->assertSame(Severity::Moderate, Blasp::nepali()->check('kukur')->severity());
        $this->assertSame(Severity::High, Blasp::nepali()->check('muji')->severity());
        $this->assertSame(Severity::Extreme, Blasp::nepali()->check('hijada')->severity());

        $this->assertFalse(Blasp::nepali()->withSeverity(Severity::High)->check('ta gadha')->isOffensive());
        $this->assertTrue(Blasp::nepali()->withSeverity(Severity::High)->check('ta muji')->isOffensive());
    }

    public function test_pattern_driver_matches_devanagari_word_boundaries()
    {
        $result = Blasp::nepali()->driver('pattern')->check('त्यो मान्छे मुजी हो');

        $this->assertTrue($result->isOffensive());
        $this->assertSame('त्यो मान्छे **** हो', $result->clean());
    }

    public function test_included_in_all_languages_check()
    {
        $this->assertTrue(Blasp::inAllLanguages()->check('k gardai xas muji')->isOffensive());
        $this->assertTrue(Blasp::inAllLanguages()->check('ए मुजी')->isOffensive());
    }

    public function test_spanish_lado_is_not_flagged_when_spanish_is_loaded()
    {
        // "lado" is Nepali profanity but an everyday Spanish word ("side")
        $this->assertTrue(Blasp::nepali()->check('mero lado')->isOffensive());
        $this->assertFalse(Blasp::in('spanish', 'nepali')->check('al lado de la casa')->isOffensive());
        $this->assertFalse(Blasp::inAllLanguages()->check('al lado de la casa')->isOffensive());
    }

    public function test_english_only_check_ignores_nepali()
    {
        $this->assertFalse(Blasp::english()->check('मुजी')->isOffensive());
    }
}
