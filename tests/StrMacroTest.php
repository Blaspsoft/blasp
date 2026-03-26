<?php

namespace Blaspsoft\Blasp\Tests;

use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

class StrMacroTest extends TestCase
{
    public function test_str_is_profane_returns_true_for_profane_text()
    {
        $this->assertTrue(Str::isProfane('fuck'));
    }

    public function test_str_is_profane_returns_false_for_clean_text()
    {
        $this->assertFalse(Str::isProfane('hello'));
    }

    public function test_str_clean_profanity_masks_profane_text()
    {
        $result = Str::cleanProfanity('fuck this');

        $this->assertStringContainsString('*', $result);
        $this->assertStringNotContainsString('fuck', $result);
    }

    public function test_str_clean_profanity_returns_clean_text_unchanged()
    {
        $this->assertSame('hello', Str::cleanProfanity('hello'));
    }

    public function test_stringable_is_profane_returns_true_for_profane_text()
    {
        $this->assertTrue(Str::of('fuck')->isProfane());
    }

    public function test_stringable_is_profane_returns_false_for_clean_text()
    {
        $this->assertFalse(Str::of('hello')->isProfane());
    }

    public function test_stringable_clean_profanity_returns_stringable_instance()
    {
        $result = Str::of('fuck this')->cleanProfanity();

        $this->assertInstanceOf(Stringable::class, $result);
        $this->assertStringContainsString('*', (string) $result);
        $this->assertStringNotContainsString('fuck', (string) $result);
    }

    public function test_stringable_clean_profanity_returns_clean_text_unchanged()
    {
        $this->assertSame('hello', (string) Str::of('hello')->cleanProfanity());
    }
}
