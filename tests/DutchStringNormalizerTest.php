<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Core\Normalizers\DutchNormalizer;

class DutchStringNormalizerTest extends TestCase
{
    private DutchNormalizer $normalizer;

    public function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new DutchNormalizer();
    }

    public function test_normalize_dutch_profanity_variants()
    {
        // kut with trema obfuscation
        $this->assertEquals('kut', $this->normalizer->normalize('küt'));
        $this->assertEquals('kut', $this->normalizer->normalize('kût'));
        $this->assertEquals('lul', $this->normalizer->normalize('lül'));
        $this->assertEquals('neuken', $this->normalizer->normalize('neüken'));
        $this->assertEquals('schijt', $this->normalizer->normalize('schïjt'));
    }

    public function test_normalize_common_dutch_words_with_diacritics()
    {
        $this->assertEquals('een', $this->normalizer->normalize('één'));
        $this->assertEquals('meteen', $this->normalizer->normalize('metëen'));
        $this->assertEquals('voor', $this->normalizer->normalize('vóór'));
    }

    public function test_normalize_mixed_case_preservation()
    {
        $this->assertEquals('KUT', $this->normalizer->normalize('KÜT'));
        $this->assertEquals('Lul', $this->normalizer->normalize('Lül'));
        $this->assertEquals('NEUKEN', $this->normalizer->normalize('NEÜKEN'));
    }

    public function test_normalize_dutch_profanities_from_config()
    {
        $config = require __DIR__ . '/../config/languages/dutch.php';
        $profanities = array_slice($config['profanities'], 0, 20);

        foreach ($profanities as $profanity) {
            $normalized = $this->normalizer->normalize($profanity);
            $this->assertDoesNotMatchRegularExpression(
                '/[àâäáèéêëìíîïòóôöùúûüÀÂÄÁÈÉÊËÌÍÎÏÒÓÔÖÙÚÛÜ]/',
                $normalized,
                "Dutch profanity '$profanity' still contains diacritics after normalization: '$normalized'"
            );
        }
    }
}
