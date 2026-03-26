<?php

namespace Blaspsoft\Blasp\Core;

use Blaspsoft\Blasp\Core\Contracts\DriverInterface;
use Blaspsoft\Blasp\Core\Contracts\MaskStrategyInterface;
use Blaspsoft\Blasp\Core\Masking\CharacterMask;

class Analyzer
{
    public function analyze(
        string $text,
        DriverInterface $driver,
        Dictionary $dictionary,
        ?MaskStrategyInterface $mask = null,
        array $options = [],
    ): Result {
        $mask = $mask ?? new CharacterMask(config('blasp.mask', config('blasp.mask_character', '*')));

        // Strip invisible Unicode format characters (zero-width spaces, invisible separators, etc.)
        // before any driver sees the text, ensuring consistent positions across pipeline drivers
        $text = preg_replace('/\p{Cf}/u', '', $text);

        return $driver->detect($text, $dictionary, $mask, $options);
    }
}
