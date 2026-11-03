<?php

namespace Blaspsoft\Blasp\Core\Normalizers;

/**
 * Folds characters that look like Latin letters into the Latin letters they
 * imitate, so "fuсk" written with a Cyrillic "с" is matched as "fuck".
 *
 * Three families are folded: Cyrillic and Greek homoglyphs, fullwidth Latin
 * letters and digits, and precomposed accented Latin letters that the
 * substitution table does not list (ā, ǎ, ő…), which are reduced to their
 * base letter.
 *
 * Every replacement is one code point for one code point. The regex driver
 * masks the ORIGINAL text at the character positions it found in the
 * normalized text, so a normalizer that changes the length would mask the
 * wrong characters. Devanagari and other non-Latin scripts are untouched.
 */
class HomoglyphNormalizer implements StringNormalizer
{
    private const HOMOGLYPHS = [
        // Cyrillic
        'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'у' => 'y', 'х' => 'x',
        'і' => 'i', 'ѕ' => 's', 'ј' => 'j', 'һ' => 'h', 'ԁ' => 'd', 'ɡ' => 'g', 'ӏ' => 'l',
        'к' => 'k', 'т' => 't', 'в' => 'b', 'м' => 'm', 'н' => 'h', 'ӓ' => 'a', 'ԛ' => 'q', 'ԝ' => 'w',
        'А' => 'A', 'Е' => 'E', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'У' => 'Y', 'Х' => 'X',
        'І' => 'I', 'Ѕ' => 'S', 'Ј' => 'J', 'Н' => 'H', 'К' => 'K', 'Т' => 'T', 'В' => 'B', 'М' => 'M',
        // Greek
        'α' => 'a', 'ο' => 'o', 'ρ' => 'p', 'ν' => 'v', 'υ' => 'u', 'ι' => 'i', 'κ' => 'k', 'τ' => 't',
        'Α' => 'A', 'Β' => 'B', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Ι' => 'I', 'Κ' => 'K', 'Μ' => 'M',
        'Ν' => 'N', 'Ο' => 'O', 'Ρ' => 'P', 'Τ' => 'T', 'Υ' => 'Y', 'Χ' => 'X',
    ];

    private StringNormalizer $inner;

    public function __construct(?StringNormalizer $inner = null)
    {
        $this->inner = $inner ?? new EnglishNormalizer();
    }

    /**
     * The language normalizer runs first, so its own mappings (German "ä" → "ae")
     * still apply; only what it leaves behind is folded.
     */
    public function normalize(string $string): string
    {
        return self::fold($this->inner->normalize($string));
    }

    public static function fold(string $string): string
    {
        // Fast path: pure ASCII has nothing to fold.
        if (!preg_match('/[^\x00-\x7F]/', $string)) {
            return $string;
        }

        $string = strtr($string, self::HOMOGLYPHS);

        // Fullwidth A-Z, a-z, 0-9 → ASCII: the fullwidth block sits at a
        // fixed offset (U+FEE0) from the ASCII printable range.
        $string = preg_replace_callback(
            '/[\x{FF10}-\x{FF19}\x{FF21}-\x{FF3A}\x{FF41}-\x{FF5A}]/u',
            static fn (array $m) => chr(mb_ord($m[0], 'UTF-8') - 0xFEE0),
            $string
        ) ?? $string;

        // Precomposed accented Latin letters → base letter, only when the
        // decomposition starts with a single ASCII letter. Ligatures such as
        // "ﬁ" or "æ" decompose to two letters and are left alone.
        // \Normalizer comes from ext-intl or the suggested
        // symfony/polyfill-intl-normalizer package. Without either, accented
        // letters are left as they are; the other folding still applies.
        if (!class_exists(\Normalizer::class)) {
            return $string;
        }

        return preg_replace_callback(
            '/[^\x00-\x7F]/u',
            static function (array $m): string {
                $char = $m[0];
                if (!preg_match('/^\p{Latin}$/u', $char)) {
                    return $char;
                }
                $decomposed = \Normalizer::normalize($char, \Normalizer::FORM_KD);
                if ($decomposed === false || $decomposed === '') {
                    return $char;
                }
                $base = mb_substr($decomposed, 0, 1, 'UTF-8');
                $rest = mb_substr($decomposed, 1, null, 'UTF-8');

                return preg_match('/^[A-Za-z]$/', $base) && preg_match('/^\p{M}*$/u', $rest) ? $base : $char;
            },
            $string
        ) ?? $string;
    }
}
