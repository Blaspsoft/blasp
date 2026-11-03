<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;

class Issue50Test extends TestCase
{
    public function test_zwj_emoji_sequence_is_preserved_in_clean_text()
    {
        $text = "Test 💆\u{200D}♀️ emoji";
        $result = Blasp::check($text);

        $this->assertFalse($result->isOffensive());
        $this->assertSame($text, $result->clean());
        $this->assertSame($text, $result->original());
    }

    public function test_zwj_emoji_sequence_is_preserved_alongside_profanity()
    {
        $result = Blasp::check("👨\u{200D}👩\u{200D}👧 fuck this 💆\u{200D}♀️");

        $this->assertTrue($result->isOffensive());
        $this->assertSame("👨\u{200D}👩\u{200D}👧 **** this 💆\u{200D}♀️", $result->clean());
    }

    public function test_match_position_refers_to_original_text()
    {
        $result = Blasp::check("💆\u{200D}♀️ shit");
        $word = $result->words()->first();

        $this->assertSame('shit', $word->text);
        // 💆, ZWJ, ♀, variation selector, space
        $this->assertSame(5, $word->position);
        $this->assertSame(4, $word->length);
    }

    public function test_invisible_character_inside_profanity_is_masked()
    {
        $result = Blasp::check("💆\u{200D}♀️ f\u{2063}uck");

        $this->assertSame("💆\u{200D}♀️ ****", $result->clean());
        $this->assertSame("f\u{2063}uck", $result->words()->first()->text);
    }

    public function test_other_format_characters_are_preserved()
    {
        // Soft hyphen and zero-width non-joiner are legitimate in normal text
        $text = "co\u{00AD}operate and می\u{200C}خواهم";
        $result = Blasp::check($text);

        $this->assertSame($text, $result->clean());
    }

    public function test_lengthening_normalizer_does_not_read_past_input()
    {
        // The German normalizer turns ä into ae, so driver positions run past the input
        $result = Blasp::in('german')->block('fuck')->check("ää\u{200D}ä fuck");

        $this->assertTrue($result->isOffensive());
        $this->assertStringContainsString("\u{200D}", $result->clean());
    }

    public function test_callback_mask_is_called_once_per_match()
    {
        $calls = 0;
        $result = Blasp::mask(function (string $word, int $length) use (&$calls) {
            $calls++;
            return '[removed]';
        })->check("💆\u{200D}♀️ fuck and shit");

        $this->assertSame(2, $calls);
        $this->assertSame("💆\u{200D}♀️ [removed] and [removed]", $result->clean());
    }

    public function test_pipeline_driver_preserves_zwj_sequences()
    {
        $result = Blasp::pipeline('regex', 'pattern')->check("💆\u{200D}♀️ fuck");

        $this->assertSame("💆\u{200D}♀️ ****", $result->clean());
    }
}
