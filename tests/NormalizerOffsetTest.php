<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Core\Normalizers\GermanNormalizer;
use Blaspsoft\Blasp\Core\Normalizers\OffsetMap;
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

    public function test_offset_map_covers_expanded_umlaut()
    {
        $map = OffsetMap::build('blöd', fn (string $s) => (new GermanNormalizer())->normalize($s));

        // "bloed" — "oe" maps back to the single ö
        $this->assertSame([2, 1], $map->toOriginal(2, 2));
        $this->assertSame([0, 4], $map->toOriginal(0, 5));
    }
}
