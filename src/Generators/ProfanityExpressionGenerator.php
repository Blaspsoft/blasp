<?php

namespace Blaspsoft\Blasp\Generators;

use Blaspsoft\Blasp\Contracts\ExpressionGeneratorInterface;

/**
 * Profanity expression generator for creating regular expressions 
 * to detect profanities with various substitutions and separators.
 * 
 * @package Blaspsoft\Blasp\Generators
 * @author Blasp Package
 * @since 3.0.0
 */
class ProfanityExpressionGenerator implements ExpressionGeneratorInterface
{
    /**
     * Value used as a the separator placeholder.
     */
    private const SEPARATOR_PLACEHOLDER = '{!!}';

    /**
     * Escaped separator characters
     */
    private const ESCAPED_SEPARATOR_CHARACTERS = ['\s'];

    /**
     * Generate profanity expressions from the given configuration.
     *
     * @param array $profanities
     * @param array $separators
     * @param array $substitutions
     * @return array<string, string>
     */
    public function generateExpressions(array $profanities, array $separators, array $substitutions): array
    {
        $separatorExpression = $this->generateSeparatorExpression($separators);
        $substitutionExpressions = $this->generateSubstitutionExpressions($substitutions);
        
        $profanityExpressions = [];
        
        foreach ($profanities as $profanity) {
            $profanityExpressions[$profanity] = $this->generateProfanityExpression(
                $profanity,
                $substitutionExpressions,
                $separatorExpression
            );
        }

        return $profanityExpressions;
    }

    /**
     * Generate separator expression from separators array.
     *
     * @param array $separators
     * @return string
     */
    public function generateSeparatorExpression(array $separators): string
    {
        // Get all separators except period
        $normalSeparators = array_filter($separators, function($sep) {
            return $sep !== '.';
        });

        // Create the pattern for normal separators
        $pattern = $this->generateEscapedExpression($normalSeparators, self::ESCAPED_SEPARATOR_CHARACTERS);
        
        // Add period and 's' as optional characters that must be followed by a word character
        return '(?:' . $pattern . '|\.(?=\w)|(?:\s))*?';
    }

    /**
     * Generate character substitution expressions.
     *
     * @param array $substitutions
     * @return array<string, string>
     */
    public function generateSubstitutionExpressions(array $substitutions): array
    {
        $characterExpressions = [];

        foreach ($substitutions as $character => $substitutionOptions) {
            $hasMultiChar = false;
            foreach ($substitutionOptions as $option) {
                // Check if option is a genuine multi-char string (not a pre-escaped single char like \$)
                if (mb_strlen($option, 'UTF-8') > 1 && !preg_match('/^\\\\.$/u', $option)) {
                    $hasMultiChar = true;
                    break;
                }
            }

            if ($hasMultiChar) {
                // Use alternation for multi-char options: (?:sch|sh|ch|s)+
                $escaped = array_map(function ($opt) {
                    // Options that are already regex-escaped (like \$) should be kept as-is
                    if (preg_match('/^\\\\.$/u', $opt)) {
                        return $opt;
                    }
                    return preg_quote($opt, '/');
                }, $substitutionOptions);
                $characterExpressions[$character] = '(?:' . implode('|', $escaped) . ')+' . self::SEPARATOR_PLACEHOLDER;
            } else {
                $characterExpressions[$character] = $this->generateEscapedExpression($substitutionOptions, [], '+') . self::SEPARATOR_PLACEHOLDER;
            }
        }

        return $characterExpressions;
    }

    /**
     * Generate a single profanity regex expression.
     *
     * @param string $profanity
     * @param array $substitutionExpressions
     * @param string $separatorExpression
     * @return string
     */
    public function generateProfanityExpression(string $profanity, array $substitutionExpressions, string $separatorExpression): string
    {
        // Build plain-key lookup: strip regex delimiters from keys
        $plainSubstitutions = [];
        foreach ($substitutionExpressions as $pattern => $replacement) {
            $plainKey = trim($pattern, '/');
            $plainSubstitutions[$plainKey] = $replacement;
        }

        // Sort by key length descending so multi-char keys (ph, qu) match first
        uksort($plainSubstitutions, function ($a, $b) {
            return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
        });

        // Single-pass: walk through profanity, match longest key at each position
        $expression = '';
        $i = 0;
        $len = mb_strlen($profanity, 'UTF-8');

        while ($i < $len) {
            $matched = false;
            foreach ($plainSubstitutions as $key => $replacement) {
                $keyLen = mb_strlen($key, 'UTF-8');
                if ($i + $keyLen <= $len && mb_substr($profanity, $i, $keyLen, 'UTF-8') === $key) {
                    $expression .= $replacement;
                    $i += $keyLen;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $expression .= preg_quote(mb_substr($profanity, $i, 1, 'UTF-8'), '/');
                $i++;
            }
        }

        $expression = str_replace(self::SEPARATOR_PLACEHOLDER, $separatorExpression, $expression);
        $expression = '/' . $expression . '/i';

        return $expression;
    }

    /**
     * Generate an escaped regex expression from characters.
     *
     * @param array $characters
     * @param array $escapedCharacters
     * @param string $quantifier
     * @return string
     */
    private function generateEscapedExpression(array $characters = [], array $escapedCharacters = [], string $quantifier = '*?'): string
    {
        $regex = $escapedCharacters;

        foreach ($characters as $character) {
            $regex[] = preg_quote($character, '/');
        }

        return '[' . implode('', $regex) . ']' . $quantifier;
    }
}