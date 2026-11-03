<?php

namespace Blaspsoft\Blasp\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a check asks for a language that has no word list. Checking against an
 * empty list would silently flag nothing, so a typo ("englsh") disabled filtering.
 */
class UnknownLanguageException extends InvalidArgumentException
{
    public static function for(string $language, array $available): self
    {
        return new self(sprintf(
            'Blasp has no word list for the language "%s". Available languages: %s.',
            $language,
            implode(', ', $available)
        ));
    }
}
