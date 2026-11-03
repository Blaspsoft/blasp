<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;

class InputSanityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('blasp.cache.enabled', false);
    }

    public function test_empty_block_word_does_not_hang()
    {
        $result = Blasp::block('', '  ')->check('hello world, all good.');

        $this->assertFalse($result->isOffensive());
    }

    public function test_empty_block_word_from_config_does_not_hang()
    {
        // The common env pattern: explode(',', env('BLASP_BLOCK', ''))
        Config::set('blasp.block', explode(',', ''));

        $result = Blasp::check('hello world, all good.');

        $this->assertFalse($result->isOffensive());
    }

    public function test_empty_block_word_is_ignored_by_pattern_driver()
    {
        $this->assertFalse(Blasp::driver('pattern')->block('')->check('hello world')->isOffensive());
        $this->assertFalse(Blasp::driver('pattern')->block('')->check("\u{200B}hi")->isOffensive());
    }

    public function test_blank_words_are_ignored_but_real_ones_still_apply()
    {
        $result = Blasp::block('', 'zorbleflap')->allow('', ' ')->check('a zorbleflap here');

        $this->assertSame(['zorbleflap'], $result->uniqueWords());
    }

    public function test_numeric_block_word_still_applies()
    {
        Config::set('blasp.block', [1337]);

        $this->assertTrue(Blasp::check('code 1337 here')->isOffensive());
    }

    public function test_pipeline_driver_handles_invalid_utf8()
    {
        $result = Blasp::driver('pipeline')->check("fuck\xFF you");

        $this->assertTrue($result->isOffensive());
    }

    public function test_invalid_utf8_does_not_disable_invisible_character_stripping()
    {
        $this->assertTrue(Blasp::check("f\u{200B}uck\xC3(")->isOffensive());
    }
}
