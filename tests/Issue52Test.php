<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;

class Issue52Test extends TestCase
{
    public function test_blocked_word_is_detected_inside_longer_word()
    {
        $result = Blasp::allLanguages()->block('appleacre')->check('my crappleacre');

        $this->assertContains('appleacre', $result->uniqueWords());
    }

    public function test_blocked_word_is_detected_inside_username()
    {
        // Issue #51
        $result = Blasp::allLanguages()->block('foo', 'bar')->check('fooapple');

        $this->assertTrue($result->isOffensive());
        $this->assertSame('***apple', $result->clean());
    }

    public function test_blocked_word_from_config_is_detected_inside_longer_word()
    {
        Config::set('blasp.block', ['appleacre']);

        $result = Blasp::check('xappleacrex');

        $this->assertContains('appleacre', $result->uniqueWords());
    }

    public function test_allow_list_overrides_blocked_word()
    {
        $result = Blasp::block('scam')->allow('scam')->check('scampi');

        $this->assertFalse($result->isOffensive());
    }

    public function test_dictionary_words_still_skipped_inside_longer_words()
    {
        $result = Blasp::block('appleacre')->check('the classroom was full of passion');

        $this->assertFalse($result->isOffensive());
    }
}
