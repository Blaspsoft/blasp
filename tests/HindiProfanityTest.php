<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Core\Dictionary;
use Blaspsoft\Blasp\Enums\Severity;
use Blaspsoft\Blasp\Facades\Blasp;

class HindiProfanityTest extends TestCase
{
    public function test_hindi_is_an_available_language()
    {
        $this->assertContains('hindi', Dictionary::getAvailableLanguages());

        $config = Dictionary::loadLanguageConfig('hindi');
        $this->assertNotEmpty($config['profanities']);
        $this->assertArrayHasKey('severity', $config);
    }

    public function test_detects_devanagari_profanities()
    {
        $cases = [
            'चूतिया' => 'तू एक चूतिया है',
            'मादरचोद' => 'साले मादरचोद',
            'भोसडी' => 'भोसडी के',
            'गांड' => 'तेरी गांड',
            'रंडी' => 'वो रंडी है',
            'हरामी' => 'बहुत हरामी आदमी है',
            'कुत्ता' => 'कुत्ता कहीं का',
        ];

        foreach ($cases as $word => $text) {
            $result = Blasp::hindi()->check($text);
            $this->assertTrue($result->isOffensive(), "Failed to detect Hindi: $word");
            $this->assertStringNotContainsString($word, $result->clean(), "$word was not masked");
            $this->assertStringContainsString('*', $result->clean());
        }
    }

    public function test_detects_romanized_profanities()
    {
        $cases = [
            'chutiya' => 'tu ek chutiya hai',
            'madarchod' => 'saale madarchod',
            'bhosdike' => 'abe bhosdike',
            'gaand' => 'teri gaand',
            'lund' => 'mera lund',
            'randi' => 'wo randi hai',
            'harami' => 'bahut harami aadmi hai',
            'bsdk' => 'chal bsdk',
        ];

        foreach ($cases as $word => $text) {
            $result = Blasp::hindi()->check($text);
            $this->assertTrue($result->isOffensive(), "Failed to detect romanized Hindi: $word");
            $this->assertStringNotContainsString($word, strtolower($result->clean()), "$word was not masked");
        }
    }

    public function test_detects_mixed_script_sentence()
    {
        $result = Blasp::hindi()->check('yeh banda चूतिया hai aur bhosdike bhi');

        $this->assertTrue($result->isOffensive());
        $this->assertSame(2, $result->count());
        $this->assertSame('yeh banda ****** hai aur ******** bhi', $result->clean());
    }

    public function test_masking_keeps_correct_positions_in_devanagari()
    {
        $result = Blasp::hindi()->check('तू एक चूतिया है');

        $this->assertSame('तू एक ****** है', $result->clean());
        $this->assertSame('चूतिया', $result->uniqueWords()[0] ?? null);
    }

    public function test_matches_nukta_variants()
    {
        // Decomposed nukta: ड + U+093C
        $this->assertTrue(Blasp::hindi()->check("भोसड\u{093C}ी के")->isOffensive());
        // Precomposed nukta: U+095C
        $this->assertTrue(Blasp::hindi()->check("भोस\u{095C}ी के")->isOffensive());
        // Masking still covers the whole word with the nukta present
        $this->assertSame('****** है', Blasp::hindi()->check("भोसड\u{093C}ी है")->clean());
    }

    public function test_matches_short_and_long_vowel_variants()
    {
        $this->assertTrue(Blasp::hindi()->check('चुतिया')->isOffensive()); // ु instead of ू
        $this->assertTrue(Blasp::hindi()->check('चूतीया')->isOffensive()); // ी instead of ि
    }

    public function test_matches_chandrabindu_variant()
    {
        $this->assertTrue(Blasp::hindi()->check('तेरी गाँड')->isOffensive());
        $this->assertSame('तेरी ****', Blasp::hindi()->check('तेरी गाँड')->clean());
    }

    public function test_matches_separated_devanagari_characters()
    {
        $this->assertTrue(Blasp::hindi()->check('मा-दर-चोद')->isOffensive());
        $this->assertTrue(Blasp::hindi()->check('चू.ति.या')->isOffensive());
    }

    public function test_matches_romanized_transliteration_variants()
    {
        $variants = ['chootiya', 'chuteeya', 'kameena', 'bhadva', 'bhadwa', 'haramjada', 'phuddu', 'CHUTIYA', 'ChUtIyA', 'c.h.u.t.i.y.a'];

        foreach ($variants as $variant) {
            $this->assertTrue(Blasp::hindi()->check($variant)->isOffensive(), "Failed to detect variant: $variant");
        }
    }

    public function test_false_positives_are_not_flagged()
    {
        $clean = [
            'मैं लंडन गया था',          // London contains लंड
            'गांडीव अर्जुन का धनुष है',  // Gandiva contains गांड
            'गरम मसाला डालो',           // masala contains साला
            'पुलिस मुठभेड़ में',         // encounter contains मुठ
            'यह बुरा है',               // bura — बुर is intentionally not listed
            'संपादक ने कहा',            // sampadak — पाद is intentionally not listed
            'chutney is tasty',
            'the parachute opened',
            'we visited Uganda',
        ];

        foreach ($clean as $text) {
            $result = Blasp::hindi()->check($text);
            $this->assertFalse($result->isOffensive(), "False positive on: $text -> {$result->clean()}");
        }
    }

    public function test_severity_levels()
    {
        $this->assertSame(Severity::Mild, Blasp::hindi()->check('साला')->severity());
        $this->assertSame(Severity::Moderate, Blasp::hindi()->check('harami')->severity());
        $this->assertSame(Severity::High, Blasp::hindi()->check('madarchod')->severity());
        $this->assertSame(Severity::Extreme, Blasp::hindi()->check('हिजडा')->severity());

        $this->assertFalse(Blasp::hindi()->withSeverity(Severity::High)->check('tu saala')->isOffensive());
        $this->assertTrue(Blasp::hindi()->withSeverity(Severity::High)->check('tu chutiya')->isOffensive());
    }

    public function test_pattern_driver_matches_devanagari_word_boundaries()
    {
        $result = Blasp::hindi()->driver('pattern')->check('तू एक चूतिया है');

        $this->assertTrue($result->isOffensive());
        $this->assertSame('तू एक ****** है', $result->clean());

        $this->assertFalse(Blasp::hindi()->driver('pattern')->check('मैं लंडन गया')->isOffensive());
    }

    public function test_included_in_all_languages_check()
    {
        $this->assertTrue(Blasp::inAllLanguages()->check('tu chutiya hai')->isOffensive());
        $this->assertTrue(Blasp::inAllLanguages()->check('तू चूतिया है')->isOffensive());
        $this->assertTrue(Blasp::in('english', 'hindi')->check('what a bhosdike')->isOffensive());
    }

    public function test_english_only_check_ignores_hindi()
    {
        $this->assertFalse(Blasp::english()->check('चूतिया')->isOffensive());
    }
}
