<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Core\Dictionary;
use Blaspsoft\Blasp\Core\Normalizers\DevanagariNormalizer;

class DevanagariNormalizerTest extends TestCase
{
    private DevanagariNormalizer $normalizer;

    public function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new DevanagariNormalizer();
    }

    public function test_chandrabindu_becomes_anusvara()
    {
        $this->assertEquals('हां', $this->normalizer->normalize('हाँ'));
        $this->assertEquals('गांड', $this->normalizer->normalize('गाँड'));
    }

    public function test_precomposed_nukta_consonants_become_base_consonants()
    {
        $this->assertEquals("ड", $this->normalizer->normalize("\u{095C}"));
        $this->assertEquals("ज", $this->normalizer->normalize("\u{095B}"));
        $this->assertEquals("फ", $this->normalizer->normalize("\u{095E}"));
    }

    public function test_decomposed_nukta_is_left_alone()
    {
        // Standalone nukta (U+093C) is handled by substitutions, not stripped,
        // so character positions stay aligned with the input
        $this->assertEquals("ड\u{093C}", $this->normalizer->normalize("ड\u{093C}"));
    }

    public function test_devanagari_digits_become_ascii()
    {
        $this->assertEquals('2081', $this->normalizer->normalize('२०८१'));
    }

    public function test_normalization_preserves_length()
    {
        $inputs = ['हाँ जी', "ड़ज़फ़", 'साधारण पाठ १२३', 'hello मुजी'];
        foreach ($inputs as $input) {
            $this->assertEquals(
                mb_strlen($input, 'UTF-8'),
                mb_strlen($this->normalizer->normalize($input), 'UTF-8'),
                "Length changed for: $input"
            );
        }
    }

    public function test_preserves_non_devanagari_text()
    {
        $this->assertEquals('hello world 123', $this->normalizer->normalize('hello world 123'));
        $this->assertEquals('cabrón', $this->normalizer->normalize('cabrón'));
    }

    public function test_registered_for_hindi_and_nepali()
    {
        $this->assertInstanceOf(DevanagariNormalizer::class, Dictionary::getNormalizerForLanguage('hindi'));
        $this->assertInstanceOf(DevanagariNormalizer::class, Dictionary::getNormalizerForLanguage('nepali'));
    }
}
