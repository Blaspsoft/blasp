<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;

class PhoneticAccuracyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('blasp.cache.enabled', false);
    }

    public function test_everyday_prose_is_not_flagged()
    {
        $prose = 'The quick brown fox jumps over the lazy dog. It was a great day and everyone was nice. '
            . 'Sarah presented the sales figures despite a slow start. We fed the ducks near the pond, '
            . 'the kids played football and the weather turned cold. Pass the salt and shake the bottle.';

        $result = Blasp::driver('phonetic')->check($prose);

        $this->assertFalse($result->isOffensive(), 'Flagged: ' . implode(', ', $result->words()->map(fn ($w) => "{$w->text}>{$w->base}")->all()));
    }

    public static function evasions(): array
    {
        return [['phuck'], ['fuk'], ['sheit'], ['phucking'], ['phuk'], ['shyt'], ['biatch'], ['kunt'], ['phag'], ['wh0re'], ['muthafukka']];
    }

    #[DataProvider('evasions')]
    public function test_sound_alike_evasions_are_still_detected(string $word)
    {
        $this->assertTrue(Blasp::driver('phonetic')->check("you {$word}")->isOffensive());
    }

    public function test_allowed_word_is_not_flagged_as_a_sound_alike()
    {
        $this->assertFalse(Blasp::driver('phonetic')->allow('fuck')->check('fuck')->isOffensive());
    }

    public function test_pipeline_default_does_not_flag_everyday_words()
    {
        $result = Blasp::driver('pipeline')->check('It was a great day, the dog was lazy and the fox was quick.');

        $this->assertFalse($result->isOffensive());
    }
}
