<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Exceptions\UnknownLanguageException;
use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;

class UnknownLanguageTest extends TestCase
{
    public function test_misspelt_language_throws_instead_of_flagging_nothing()
    {
        $this->expectException(UnknownLanguageException::class);
        $this->expectExceptionMessage('"englsh"');

        Blasp::in('englsh')->check('fuck you');
    }

    public function test_misspelt_language_in_a_list_throws()
    {
        $this->expectException(UnknownLanguageException::class);

        Blasp::in('english', 'spansh')->check('fuck you');
    }

    public function test_misspelt_default_language_throws()
    {
        Config::set('blasp.language', 'englsh');

        $this->expectException(UnknownLanguageException::class);

        Blasp::check('fuck you');
    }

    public function test_language_names_are_case_and_space_insensitive()
    {
        $this->assertTrue(Blasp::in('English')->check('fuck you')->isOffensive());
        $this->assertTrue(Blasp::in(' SPANISH ')->check('mierda')->isOffensive());
        $this->assertTrue(Blasp::in('English', 'Spanish')->check('mierda')->isOffensive());
    }

    public function test_message_lists_available_languages()
    {
        try {
            Blasp::in('klingon')->check('hello');
            $this->fail('Expected an UnknownLanguageException');
        } catch (UnknownLanguageException $e) {
            $this->assertStringContainsString('english', $e->getMessage());
            $this->assertStringContainsString('spanish', $e->getMessage());
        }
    }
}
