<?php

/*
|--------------------------------------------------------------------------
| Hindi
|--------------------------------------------------------------------------
|
| Each severity level lists both Devanagari and romanized (Latin-script)
| spellings, since Hindi profanity online is written in both.
|
| Devanagari conventions used in this file:
|  - Words are written WITHOUT nukta (ड not ड़, ज not ज़). The substitutions
|    below make the base consonant also match its nukta and precomposed forms,
|    so भोसडी matches भोसड़ी as well.
|  - Anusvara (ं) is used instead of chandrabindu (ँ); the DevanagariNormalizer
|    folds ँ into ं before matching.
|  - Short/long vowel signs (ि/ी, ु/ू) are interchangeable via substitutions,
|    so चुतिया and चूतिया both match.
|
| Romanized spellings: repeated letters are already tolerated by the regex
| engine (gaand/gand, saala/sala), and the substitutions below add the common
| transliteration swaps u/oo, i/ee, v/w, z/j and f/ph.
|
| Deliberately omitted: caste-based slurs (legitimate community names) and
| "chakka" (also means a cricket six). Add them via `block()` if needed.
|
*/

$severity = [
    'mild' => [
        // Devanagari
        'साला', 'साली', 'सालो', 'गधा', 'गधे', 'गधी',
        'उल्लू', 'उल्लू का पट्ठा', 'उल्लू के पट्ठे',
        'बेवकूफ', 'बेवकूफी', 'सुअर', 'टट्टी', 'पादना', 'मूतना',
        'चूतड', 'नालायक', 'लफंगा', 'बदतमीज',
        // Romanized
        'saala', 'saale', 'saali', 'gadha', 'gadhe', 'gadhi',
        'ullu', 'ullu ka pattha', 'ullu ke patthe',
        'bewakoof', 'bevkoof', 'bewkoof', 'bewakoofi', 'suar', 'suwar',
        'tatti', 'paadna', 'chutad', 'nalayak', 'lafanga', 'badtameez',
    ],
    'moderate' => [
        // Devanagari
        'हरामी', 'हरामखोर', 'कमीना', 'कमीने', 'कमीनी',
        'कुत्ता', 'कुत्ते', 'कुत्ती', 'कुतिया', 'कुत्ते की औलाद', 'कुत्ते का पिल्ला',
        'बकचोद', 'बकचोदी', 'नामर्द', 'चूची', 'चूचे',
        'मुठ', 'मुठ मारना', 'मुठ मार', 'लोडू', 'फुद्दू', 'टट्टे',
        'झाटू', 'झांटू', 'छपरी',
        'हवस', 'हवस के पुजारी', 'थरक', 'थरकी', 'भूतनी के', 'बूबे',
        // Romanized
        'harami', 'haramkhor', 'kamina', 'kameena', 'kamine', 'kameene', 'kamini', 'kameeni',
        'kutta', 'kutte', 'kutti', 'kutiya', 'kuttiya', 'kutte ki aulad', 'kutte ka pilla',
        'bakchod', 'bakchodi', 'namard', 'chuchi', 'chuche',
        'muth', 'muth marna', 'muth maar', 'lodu', 'fuddu', 'tatte',
        'jhatu', 'jhantu', 'chapri',
        'hawas', 'hawas ke pujari', 'tharak', 'tharki', 'bhootni ke', 'boobe',
    ],
    'high' => [
        // Devanagari
        'चूतिया', 'चूतिये', 'चूतियो', 'चूतियापा', 'चूतियापंती',
        'चूत', 'चूत के ढक्कन', 'चूतमरीके',
        'चोद', 'चोदू', 'चोदना', 'चोदा', 'चोदी', 'चोदने',
        'लंड', 'लौडा', 'लौडे', 'लोडा', 'लोडे', 'लवडा', 'लवडे',
        'गांड', 'गांडू', 'गान्डु', 'गान्डू', 'गांड मरा', 'गांड मराना', 'गांड फाड',
        'भोसडा', 'भोसडी', 'भोसडी के', 'भोसडीके', 'भोसडीवाला', 'भोसडीवाले',
        'भडवा', 'भडवे', 'भडुआ',
        'रंडी', 'रांड', 'रंडवा', 'रंडीबाज', 'रंडी का बच्चा',
        'छिनाल', 'चिनाल', 'झांट', 'झाट',
        'हरामजादा', 'हरामजादे', 'हरामजादी',
        'तेरी मां की चूत', 'मां की चूत', 'मां का भोसडा', 'तेरी मां का भोसडा',
        'मादरचोद', 'मादरजात', 'बहनचोद', 'बहन के लौडे', 'बहन के लोडे', 'बेटीचोद',
        'आंड', 'आंडू', 'बलात्कार', 'बलात्कारी', 'भंडवे',
        'चुदाप', 'चुदाई खाना', 'चुदम चुदाई', 'चुदे', 'चुतन', 'चूपे',
        'गंडफट्टू', 'गश्ती', 'गस्ती', 'घस्सा', 'घस्ती', 'गुच्ची', 'गुच्चू',
        'लंडटोपी', 'लंडूरे', 'माधवचोद', 'मुंह में ले', 'रंडाप',
        'तेरी मां का भोसाडा', 'तेरी मां का बोबा चूसू', 'तू चुदा',
        'नजायज औलाद', 'नजायज पैदाइश',
        // Romanized
        'chutiya', 'chutiye', 'chutiyo', 'chutia', 'chutiyapa', 'chutiyapanti',
        'chut', 'chut ke dhakkan', 'chutmarike', 'chut marike',
        'chod', 'chodu', 'chodna', 'choda', 'chodi', 'chodne',
        'lund', 'lauda', 'laude', 'laudu', 'lawda', 'lawde', 'loda', 'lavda', 'lavde',
        'gaand', 'gand', 'gandu', 'gaandu', 'gaand mara', 'gand mara', 'gaand marna', 'gaand faad',
        'bhosda', 'bhosdi', 'bhosdike', 'bhosdi ke', 'bhosdiki', 'bhosdiwala', 'bhosdiwale',
        'bhosadike', 'bhosad', 'bsdk', 'bkl',
        'bhadwa', 'bhadwe', 'bhadva', 'bharwa', 'bhadua',
        'randi', 'raand', 'randwa', 'randibaaz', 'randi ka bacha', 'randi ka baccha',
        'chinal', 'jhaat', 'jhant',
        'haramzada', 'haramzade', 'haramzadi',
        'teri maa ki chut', 'maa ki chut', 'maa ka bhosda', 'teri maa ka bhosda', 'mkc',
        'madarchod', 'maderchod', 'madarchodh', 'motherchod', 'madarjaat',
        'behenchod', 'bhenchod', 'behnchod', 'bahenchod', 'bahinchod', 'benchod',
        'bhen ke laude', 'behen ke laude', 'bhen ke lode', 'behen ke lode', 'betichod',
        'aand', 'aandu', 'balatkar', 'balatkari', 'bhandve',
        'chudaap', 'chudai khanaa', 'chudam chudai', 'chude', 'chutan', 'choope',
        'gandfattu', 'gashti', 'gasti', 'ghassa', 'ghasti', 'gucchi', 'gucchu',
        'lundtopi', 'lundure', 'madhavchod', 'mooh mein le', 'randaap',
        'teri maa ka bhosada', 'teri maa ka boba chusu', 'tu chuda',
        'najayaz aulaad', 'najayaz paidaish',
    ],
    'extreme' => [
        // Devanagari
        'हिजडा', 'हिजडे', 'हिजरा', 'कटुआ', 'चिंकी',
        // Romanized
        'hijda', 'hijde', 'hijra', 'katua', 'chinki', 'paki',
    ],
];

return [
    'severity' => $severity,

    'profanities' => array_values(array_unique(array_merge(...array_values($severity)))),

    'false_positives' => [
        // Legitimate words containing a listed word as a substring
        'लंडन',                       // London (contains लंड)
        'गांडीव',                     // Gandiva, Arjuna's bow (contains गांड)
        'मसाला', 'मसाले', 'मसालों', 'मसालेदार', 'सालाना', // contain साला
        'मुठभेड', "मुठभेड\u{093C}", 'मुठ्ठी', 'मुठिया', 'मूठ', // contain मुठ
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
