<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;

class AllLanguagesDetectionTest extends TestCase
{
    public function test_all_languages_profanity_detection()
    {
        $testCases = [
            'english' => [
                'text' => 'You are a fucking cunt',
                'expected_profanities' => ['fucking', 'cunt'],
                'min_count' => 2
            ],
            'german' => [
                'text' => 'Du bist eine verdammte Fotze',
                'expected_profanities' => ['verdammte', 'fotze'],
                'min_count' => 2
            ],
            'french' => [
                'text' => 'Tu es un putain de connard',
                'expected_profanities' => ['putain', 'connard'],
                'min_count' => 2
            ],
            'spanish' => [
                'text' => 'Eres un maldito hijo de puta',
                'expected_profanities' => ['maldito', 'hijo de puta', 'puta'],
                'min_count' => 2
            ],
            'hindi' => [
                'text' => 'तू चूतिया है aur madarchod bhi',
                'expected_profanities' => ['चूतिया', 'madarchod'],
                'min_count' => 2
            ],
            'nepali' => [
                'text' => 'ए मुजी machikne',
                'expected_profanities' => ['मुजी', 'machikne'],
                'min_count' => 2
            ]
        ];

        foreach ($testCases as $language => $testCase) {
            $result = Blasp::in($language)->check($testCase['text']);

            $this->assertTrue(
                $result->isOffensive(),
                "[$language] Failed to detect profanities in: {$testCase['text']}"
            );

            $this->assertGreaterThanOrEqual(
                $testCase['min_count'],
                $result->count(),
                "[$language] Expected at least {$testCase['min_count']} profanities, got {$result->count()}"
            );

            foreach ($testCase['expected_profanities'] as $profanity) {
                $this->assertStringNotContainsString(
                    $profanity,
                    strtolower($result->clean()),
                    "[$language] '$profanity' was not censored"
                );
            }

            $this->assertStringContainsString(
                '*',
                $result->clean(),
                "[$language] No asterisks found in censored string"
            );
        }
    }

    public function test_language_variations()
    {
        $variations = [
            'german' => [
                'verdammte' => ['VERDAMMTE', 'Verdammte', 'verdammte', 'VeRdAmMtE'],
                'scheisse' => ['SCHEISSE', 'Scheisse', 'scheisse', 'ScHeIsSe', 'scheisse']
            ],
            'french' => [
                'merde' => ['MERDE', 'Merde', 'merde', 'MeRdE'],
                'putain' => ['PUTAIN', 'Putain', 'putain', 'PuTaIn']
            ],
            'spanish' => [
                'mierda' => ['MIERDA', 'Mierda', 'mierda', 'MiErDa'],
                'joder' => ['JODER', 'Joder', 'joder', 'JoDeR']
            ],
            'hindi' => [
                'chutiya' => ['CHUTIYA', 'Chutiya', 'chutiya', 'ChUtIyA', 'चूतिया', 'चुतिया'],
                'madarchod' => ['MADARCHOD', 'Madarchod', 'madarchod', 'मादरचोद'],
            ],
            'nepali' => [
                'muji' => ['MUJI', 'Muji', 'muji', 'MuJi', 'मुजी', 'मुजि'],
                'machikne' => ['MACHIKNE', 'Machikne', 'machikne', 'माछिक्ने'],
            ],
            'english' => [
                'fuck' => ['FUCK', 'Fuck', 'fuck', 'FuCk', 'f@ck', 'f*ck'],
                'shit' => ['SHIT', 'Shit', 'shit', 'ShIt', 'sh1t', 'sh!t']
            ]
        ];

        foreach ($variations as $language => $words) {
            foreach ($words as $base => $variants) {
                foreach ($variants as $variant) {
                    $testText = "This contains $variant here";
                    $result = Blasp::in($language)->check($testText);

                    $this->assertTrue(
                        $result->isOffensive(),
                        "[$language] Failed to detect variant '$variant' of '$base'"
                    );
                }
            }
        }
    }

    public function test_language_normalizers()
    {
        // German-specific: umlauts and eszett
        $germanTests = ['scheisse', 'Scheisse', 'SCHEISSE'];

        foreach ($germanTests as $input) {
            $result = Blasp::german()->check("Das ist $input test");
            $this->assertTrue(
                $result->isOffensive(),
                "German normalizer failed for '$input'"
            );
        }

        // French-specific: accents
        $frenchTests = ['connard', 'CONNARD', 'Connard'];

        foreach ($frenchTests as $input) {
            $result = Blasp::french()->check("C'est un $input ici");
            $this->assertTrue(
                $result->isOffensive(),
                "French normalizer failed for '$input'"
            );
        }
    }
}
