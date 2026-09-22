<?php

/*
|--------------------------------------------------------------------------
| Nepali
|--------------------------------------------------------------------------
|
| Each severity level lists both Devanagari and romanized (Latin-script)
| spellings, since Nepali profanity online is written in both.
|
| Devanagari conventions used in this file:
|  - Words are written WITHOUT nukta; the substitutions below make the base
|    consonant also match its nukta and precomposed forms.
|  - Anusvara (ं) is used instead of chandrabindu (ँ); the DevanagariNormalizer
|    folds ँ into ं before matching, so गाँड matches the गांड entry.
|  - Short/long vowel signs (ि/ी, ु/ू) are interchangeable via substitutions,
|    so मुजि and मुजी both match. Spellings that differ structurally
|    (रण्डी / रन्डी / रंडी) are listed separately.
|
| Romanized spellings: repeated letters are already tolerated by the regex
| engine (gaad/gaaad, choro/chhoro), and the substitutions below add the
| common transliteration swaps u/oo, i/ee, v/w, z/j and f/ph.
|
| Deliberately omitted: ethnic slurs that double as legitimate community
| names, and "chakka" (also a cricket six). Add them via `block()` if needed.
|
*/

$severity = [
    'mild' => [
        // Devanagari
        'साला', 'साली', 'गधा', 'गधे', 'मूर्ख', 'बेकुफ', 'बेवकुफ',
        'पाजी', 'गोरु', 'फटाहा', 'लुते', 'साले',
        // Romanized
        'saala', 'saali', 'saale', 'gadha', 'gadhe', 'murkha', 'bekuf', 'bewakuf',
        'paji', 'goru', 'fataha', 'lute', 'lutte',
    ],
    'moderate' => [
        // Devanagari
        'कुकुर', 'कुकुरनी', 'कुकुरको छोरो', 'कुकुरको छोरी', 'कुत्ता', 'कुत्ती',
        'सुंगुर', 'बोका', 'बोक्सी', 'भालु', 'गु खा', 'गु खाने',
        'हरामी', 'कमिना', 'कमीना',
        // Romanized
        'kukur', 'kukurni', 'kukurko choro', 'kukur ko choro', 'kukurko chori', 'kukur ko chori',
        'kutta', 'kutti', 'sungur', 'boka', 'boksi', 'bhalu', 'gu kha', 'gu khane',
        'harami', 'kamina', 'kameena',
    ],
    'high' => [
        // Devanagari
        'मुजी', 'मुजी खाते', 'मुजीखाते',
        'माछिक्ने', 'मछिक्ने', 'माछिक्नी', 'मछिक्नी',
        'चिक्नु', 'चिक्ने', 'चिकेको', 'चिक्यो',
        'आमा चिक्ने', 'तेरो आमा चिक्ने', 'बाउ चिक्ने', 'बहिनी चिक्ने', 'दिदी चिक्ने',
        'पुती', 'पुतीको', 'पुती खाते', 'पुतीखाते', 'तुरी',
        'लाडो', 'लाडो चुस',
        'गांड', 'गांडु', 'गांड मार', 'गांड फाट',
        'रण्डी', 'रन्डी', 'रंडी', 'रण्डीको छोरो', 'रन्डीको छोरो', 'रंडीको छोरो',
        'रण्डी खाते', 'रन्डी खाते', 'रंडी खाते',
        'बेश्या', 'बेस्या',
        'मादरचोद', 'चूतिया', 'बहनचोद',
        'रण्डीको छोरी', 'रन्डीको छोरी', 'रंडीको छोरी',
        // Romanized
        'muji', 'mujee', 'muzi', 'muji khate', 'mujikhate',
        'machikne', 'machikni', 'machikna',
        'chiknu', 'chikne', 'chikeko', 'chikyo',
        'ama chikne', 'tero ama chikne', 'bau chikne', 'bahini chikne', 'didi chikne',
        'puti', 'putiko', 'puti khate', 'putikhate', 'turi',
        'lado', 'lado chus',
        'gaad', 'gaand', 'gaadu', 'gandu', 'gaad mar', 'gaand mar', 'gaad faat',
        'randi', 'randiko choro', 'randi ko choro', 'randi khate',
        'randiko chori', 'randi ko chori',
        'beshya', 'besya',
        'madarchod', 'maderchod', 'chutiya', 'behenchod', 'bahenchod',
    ],
    'extreme' => [
        // Devanagari
        'हिजडा', 'हिजडे',
        // Romanized
        'hijada', 'hijda', 'hijade', 'hijde',
    ],
];

return [
    'severity' => $severity,

    'profanities' => array_values(array_unique(array_merge(...array_values($severity)))),

    'false_positives' => [
        // Legitimate words containing a listed word as a substring
        'बोकाउनु', 'बोकाउने', 'बोकाएर', 'बोकाएको', 'बोकाइ', 'बोकाउँछ', 'बोकाउंछ', // contain बोका
        'मसाला', 'मसाले',             // contain साला
        'गांडीव',                     // Gandiva (contains गांड)
        'लंडन',                       // London
    ],

    'substitutions' => [
        // Base consonant also matches its nukta (base + U+093C) and precomposed forms
        '/क/' => ['क', "क\u{093C}", "\u{0958}"],
        '/ख/' => ['ख', "ख\u{093C}", "\u{0959}"],
        '/ग/' => ['ग', "ग\u{093C}", "\u{095A}"],
        '/ज/' => ['ज', "ज\u{093C}", "\u{095B}"],
        '/ड/' => ['ड', "ड\u{093C}", "\u{095C}"],
        '/ढ/' => ['ढ', "ढ\u{093C}", "\u{095D}"],
        '/फ/' => ['फ', "फ\u{093C}", "\u{095E}"],
        '/य/' => ['य', "य\u{093C}", "\u{095F}"],
        // Short/long vowel signs are commonly confused
        '/ि/' => ['ि', 'ी'],
        '/ी/' => ['ी', 'ि'],
        '/ु/' => ['ु', 'ू'],
        '/ू/' => ['ू', 'ु'],
        // Anusvara / chandrabindu
        '/ं/' => ['ं', 'ँ'],
        // Common consonant swaps in casual typing
        '/ब/' => ['ब', 'व'],
        '/व/' => ['व', 'ब'],
        '/न/' => ['न', 'ण'],
        '/ण/' => ['ण', 'न'],
        // Romanized transliteration variants
        '/u/' => ['u', 'oo'],
        '/i/' => ['i', 'ee'],
        '/v/' => ['v', 'w'],
        '/w/' => ['w', 'v'],
        '/z/' => ['z', 'j'],
        '/f/' => ['f', 'ph'],
    ],
];
