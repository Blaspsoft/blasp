<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Core\Normalizers\GermanNormalizer;
use Blaspsoft\Blasp\Core\Normalizers\OffsetMap;
use Blaspsoft\Blasp\Core\Normalizers\SpanishNormalizer;
use Blaspsoft\Blasp\Facades\Blasp;

class NormalizerOffsetTest extends TestCase
{
    public function test_repeated_whitespace_does_not_shift_masking()
    {
        $result = Blasp::english()->check('a  fuck  this shit');

        $this->assertSame('a  ****  this ****', $result->clean());
        $this->assertSame(['fuck', 'shit'], $result->words()->pluck('text')->all());
    }

    public function test_german_umlauts_and_eszett_do_not_shift_masking()
    {
        $result = Blasp::german()->check('Du blöde Scheiße, du Arschloch');

        $this->assertSame('Du ***** *******, du *********', $result->clean());
    }

    public function test_german_eszett_before_profanity()
    {
        $result = Blasp::german()->check('Straße scheiße');

        $this->assertSame('Straße *******', $result->clean());
        $this->assertSame('scheiße', $result->words()->first()->text);
    }

    public function test_spanish_ll_before_profanity()
    {
        $result = Blasp::spanish()->check('llama mierda');

        $this->assertSame('llama ******', $result->clean());
    }

    public function test_french_ligature_before_profanity()
    {
        $result = Blasp::french()->check('cœur merde');

        $this->assertSame('cœur *****', $result->clean());
    }

    public function test_umlaut_with_format_character_before_profanity()
    {
        $result = Blasp::german()->check("ää\u{200D}ä scheiße");

        $this->assertSame("ää\u{200D}ä *******", $result->clean());
    }

    public function test_phonetic_driver_maps_positions_back()
    {
        $result = Blasp::driver('phonetic')->in('english')->check('a  phuck');

        $this->assertSame('a  *****', $result->clean());
    }

    public function test_offset_map_covers_whole_sch_cluster()
    {
        $map = OffsetMap::build('Arsch!', fn (string $s) => (new GermanNormalizer())->normalize($s));

        // "Arsh!" — the normalized "Arsh" covers all five original characters
        $this->assertSame([0, 5], $map->toOriginal(0, 4));
    }

    public function test_offset_map_handles_changes_that_cancel_out()
    {
        // ä → ae and sch → sh leave "äArsch" and "aeArsh" the same length
        $map = OffsetMap::build('äArsch', fn (string $s) => (new GermanNormalizer())->normalize($s));

        $this->assertSame([1, 5], $map->toOriginal(2, 4));
    }

    public function test_offset_map_covers_context_dependent_rewrite()
    {
        // Spanish ll → y only before a vowel, so "ya" must map back to "lla"
        $map = OffsetMap::build('llama', fn (string $s) => (new SpanishNormalizer())->normalize($s));

        $this->assertSame([0, 3], $map->toOriginal(0, 2));
    }

    public function test_offset_map_handles_long_chunk_without_spaces()
    {
        // Over the prefix-scan limit, so the chunk is split at the punctuation instead
        $text = 'äsch!fick!' . str_repeat('x', 247);
        $map = OffsetMap::build($text, fn (string $s) => (new GermanNormalizer())->normalize($s));

        // Normalized "aesh!fick!…": "fick" starts at 5 in both
        $this->assertSame([5, 4], $map->toOriginal(5, 4));
        $this->assertSame([0, 4], $map->toOriginal(0, 4));
    }

    public function test_offset_map_covers_expanded_umlaut()
    {
        $map = OffsetMap::build('blöd', fn (string $s) => (new GermanNormalizer())->normalize($s));

        // "bloed" — "oe" maps back to the single ö
        $this->assertSame([2, 1], $map->toOriginal(2, 2));
        $this->assertSame([0, 4], $map->toOriginal(0, 5));
    }
}
