<?php

namespace Blaspsoft\Blasp\Core\Normalizers;

/**
 * Shared normalizer for Devanagari-script languages (Hindi, Nepali).
 *
 * Only 1:1 codepoint mappings are applied so character positions stay aligned
 * with the original text. Variations that change length (nukta stripping,
 * short/long vowel signs) are handled via per-language substitutions instead.
 */
class DevanagariNormalizer implements StringNormalizer
{
    public function normalize(string $string): string
    {
        $mappings = [
            // Chandrabindu is commonly typed as anusvara (हाँ / हां)
            "\u{0901}" => "\u{0902}",
            // Precomposed nukta consonants -> base consonant (nukta is often omitted)
            "\u{0958}" => "\u{0915}", // क़ -> क
            "\u{0959}" => "\u{0916}", // ख़ -> ख
            "\u{095A}" => "\u{0917}", // ग़ -> ग
            "\u{095B}" => "\u{091C}", // ज़ -> ज
            "\u{095C}" => "\u{0921}", // ड़ -> ड
            "\u{095D}" => "\u{0922}", // ढ़ -> ढ
            "\u{095E}" => "\u{092B}", // फ़ -> फ
            "\u{095F}" => "\u{092F}", // य़ -> य
            // Devanagari digits -> ASCII so leetspeak-style substitutions still apply
            "\u{0966}" => '0', "\u{0967}" => '1', "\u{0968}" => '2', "\u{0969}" => '3', "\u{096A}" => '4',
            "\u{096B}" => '5', "\u{096C}" => '6', "\u{096D}" => '7', "\u{096E}" => '8', "\u{096F}" => '9',
        ];

        return strtr($string, $mappings);
    }
}
