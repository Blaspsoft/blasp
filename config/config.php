<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Language
    |--------------------------------------------------------------------------
    |
    | The default language to use for profanity detection. Available languages
    | are stored in the config/languages/ directory.
    |
    */
    'default_language' => 'english',

    /*
    |--------------------------------------------------------------------------
    | Mask Character
    |--------------------------------------------------------------------------
    |
    | The character to use for masking profanities. Default is '*'.
    |
    */
    'mask_character' => '*',

    /*
    |--------------------------------------------------------------------------
    | Cache Driver
    |--------------------------------------------------------------------------
    |
    | Specify the cache driver to use for storing profanity expressions.
    | If not specified, the default Laravel cache driver will be used.
    | This is useful for environments like Laravel Vapor where DynamoDB
    | has size limits that can be exceeded by cached profanity expressions.
    |
    | Supported: Any cache driver configured in your Laravel application
    | Example: "redis", "file", "array", "database", etc.
    |
    */
    'cache_driver' => env('BLASP_CACHE_DRIVER'),

    /*
    |--------------------------------------------------------------------------
    | Character separators
    |--------------------------------------------------------------------------
    |
    | An array of special characters that could be used a separators.
    |
    |
    */
    'separators' => [
        '@',
        '#',
        '%',
        '&',
        '_',
        ';',
        "'",
        '"',
        ',',
        '~',
        '`',
        '|',
        '!',
        '$',
        '^',
        '*',
        '(',
        ')',
        '-',
        '+',
        '=',
        '{',
        '}',
        '[',
        ']',
        ':',
        '<',
        '>',
        '?',
        '.',
        '/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Character Substitutions
    |--------------------------------------------------------------------------
    |
    | An array of alpha characters and their possible substitutions.
    |
    |
    */
    'substitutions' => [
        '/a/' => ['a', '4', '@', 'Á', 'á', 'À', 'Â', 'à', 'Â', 'â', 'Ä', 'ä', 'Ã', 'ã', 'Å', 'å', 'æ', 'Æ', 'α', 'Δ', 'Λ', 'λ'],
        '/b/' => ['b', '8', '\\', '3', 'ß', 'Β', 'β'],
        '/c/' => ['c', 'Ç', 'ç', 'ć', 'Ć', 'č', 'Č', '¢', '€', '<', '(', '{', '©'],
        '/d/' => ['d', '\\', ')', 'Þ', 'þ', 'Ð', 'ð'],
        '/e/' => ['e', '3', '€', 'È', 'è', 'É', 'é', 'Ê', 'ê', 'ë', 'Ë', 'ē', 'Ē', 'ė', 'Ė', 'ę', 'Ę', '∑'],
        '/f/' => ['f', 'ƒ'],
        '/g/' => ['g', '6', '9'],
        '/h/' => ['h', 'Η'],
        '/i/' => ['i', '!', '|', ']', '[', '1', '∫', 'Ì', 'Í', 'Î', 'Ï', 'ì', 'í', 'î', 'ï', 'ī', 'Ī', 'į', 'Į'],
        '/j/' => ['j'],
        '/k/' => ['k', 'Κ', 'κ'],
        '/l/' => ['l', '!', '|', ']', '[', '£', '∫', 'Ì', 'Í', 'Î', 'Ï', 'ł', 'Ł'],
        '/m/' => ['m'],
        '/n/' => ['n', 'η', 'Ν', 'Π', 'ñ', 'Ñ', 'ń', 'Ń'],
        '/o/' => ['o', '0', 'Ο', 'ο', 'Φ', '¤', '°', 'ø', 'ô', 'Ô', 'ö', 'Ö', 'ò', 'Ò', 'ó', 'Ó', 'œ', 'Œ', 'ø', 'Ø', 'ō', 'Ō', 'õ', 'Õ'],
        '/p/' => ['p', 'ρ', 'Ρ', '¶', 'þ'],
        '/q/' => ['q'],
        '/r/' => ['r', '®'],
        '/s/' => ['s', '5', '\$', '§', 'ß', 'Ś', 'ś', 'Š', 'š'],
        '/t/' => ['t', 'Τ', 'τ'],
        '/u/' => ['u', 'υ', 'µ', 'û', 'ü', 'ù', 'ú', 'ū', 'Û', 'Ü', 'Ù', 'Ú', 'Ū', '@', '*'],
        '/v/' => ['v', 'υ', 'ν'],
        '/w/' => ['w', 'ω', 'ψ', 'Ψ'],
        '/x/' => ['x', 'Χ', 'χ'],
        '/y/' => ['y', '¥', 'γ', 'ÿ', 'ý', 'Ÿ', 'Ý'],
        '/z/' => ['z', 'Ζ', 'ž', 'Ž', 'ź', 'Ź', 'ż', 'Ż'],
    ],

    /*
    |--------------------------------------------------------------------------
    | False Positives
    |--------------------------------------------------------------------------
    |
    | An array of false positives
    |
    |
    */
    'false_positives' => [
        'hello',
        'scunthorpe',
        'cockburn',
        'penistone',
        'lightwater',
        'assume',
        'bass',
        'class',
        'compass',
        'pass',
        'dickinson',
        'middlesex',
        'cockerel',
        'butterscotch',
        'blackcock',
        'countryside',
        'arsenal',
        'flick',
        'flicker',
        'analyst',
        'cocktail',
        'musicals hit',
        'is hit',
        'blackcocktail',
        'its not',
    ],


    /*
    |--------------------------------------------------------------------------
    | Multi-Language Support
    |--------------------------------------------------------------------------
    |
    | Language-specific profanities, false positives, and substitutions are
    | now stored in separate files in the config/languages/ directory.
    | The following profanities array is kept for backward compatibility.
    |
    */
    'profanities' => [
        // Basic English profanities for backward compatibility
        // Full profanity lists are now in config/languages/english.php
        'fuck',
        'shit',
        'damn',
        'bitch',
        'ass',
        'hell',
    ],
];