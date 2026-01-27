<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;

class Issue32FalsePositiveTest extends TestCase
{
    /**
     * @dataProvider legitimateWordsProvider
     */
    public function test_legitimate_words_not_flagged(string $word)
    {
        $result = Blasp::check($word);
        $this->assertFalse(
            $result->hasProfanity(),
            "\"$word\" should not be flagged as profanity but got: " . implode(', ', $result->getUniqueProfanitiesFound())
        );
    }

    public static function legitimateWordsProvider(): array
    {
        return [
            'assignment' => ['assignment'],
            'passion' => ['passion'],
            'classroom' => ['classroom'],
            'passenger' => ['passenger'],
            'assassin' => ['assassin'],
            'massive' => ['massive'],
            'embassy' => ['embassy'],
            'harassment' => ['harassment'],
            'compassion' => ['compassion'],
            'association' => ['association'],
        ];
    }

    public function test_actual_profanity_still_detected()
    {
        $result = Blasp::check('ass');
        $this->assertTrue($result->hasProfanity());
    }
}
