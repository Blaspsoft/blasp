<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;

class WordListAdditionsTest extends TestCase
{
    public function test_new_entries_are_detected(): void
    {
        $cases = [
            'english' => ['fcuk you', 'a niqqer', 'kkk rally', 'sieg heil', 'a neonazi', 'what a poof'],
            'french' => ['quel branleur', 'espèce de pouffiasse', 'ferme ta gueule enfoire'],
            'german' => ['du vollpfosten', 'du hackfresse', 'du schwanzlutscher'],
            'spanish' => ['eres un soplapollas', 'vete a la mierda', 'menudo lameculos'],
        ];

        foreach ($cases as $language => $texts) {
            foreach ($texts as $text) {
                $this->assertTrue(Blasp::in($language)->check($text)->isOffensive(), "$language: $text");
            }
        }
    }

    public function test_ordinary_phrases_containing_new_entries_are_not_flagged(): void
    {
        foreach (['a slice of Boston cream pie', 'I love cream pie'] as $text) {
            $this->assertFalse(Blasp::check($text)->isOffensive(), $text);
        }

        $this->assertTrue(Blasp::check('watching creampie videos')->isOffensive());
    }

    public function test_ordinary_words_containing_new_entries_are_not_flagged_when_matching_embedded(): void
    {
        $cases = [
            'english' => ['we spoofed the request', 'a spoofing attack'],
            'french' => ['le pentateuque', 'le clitoris'],
        ];

        foreach ($cases as $language => $texts) {
            foreach ($texts as $text) {
                $this->assertFalse(Blasp::in($language)->matchEmbedded()->check($text)->isOffensive(), "$language: $text");
            }
        }
    }
}
