<?php

namespace Blaspsoft\Blasp\Facades;

use Illuminate\Support\Facades\Facade;
use Blaspsoft\Blasp\BlaspService;

/**
 * @method static \Blaspsoft\Blasp\BlaspService check(?string $string)
 * @method static \Blaspsoft\Blasp\BlaspService configure(?array $profanities = null, ?array $falsePositives = null)
 * @method static \Blaspsoft\Blasp\BlaspService language(string $language)
 * @method static \Blaspsoft\Blasp\BlaspService english()
 * @method static \Blaspsoft\Blasp\BlaspService spanish()
 * @method static \Blaspsoft\Blasp\BlaspService german()
 * @method static \Blaspsoft\Blasp\BlaspService french()
 * @method static \Blaspsoft\Blasp\BlaspService allLanguages()
 * @method static \Blaspsoft\Blasp\BlaspService maskWith(string $character)
 * 
 * @see \Blaspsoft\Blasp\BlaspService
 */
class Blasp extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'blasp';
    }

    /**
     * Set the language for profanity detection
     *
     * @param string $language
     * @return \Blaspsoft\Blasp\BlaspService
     */
    public static function language(string $language): BlaspService
    {
        return static::getFacadeRoot()->language($language);
    }

    /**
     * Configure profanities and false positives
     *
     * @param array|null $profanities
     * @param array|null $falsePositives
     * @return \Blaspsoft\Blasp\BlaspService
     */
    public static function configure(?array $profanities = null, ?array $falsePositives = null): BlaspService
    {
        return static::getFacadeRoot()->configure($profanities, $falsePositives);
    }

    /**
     * Set English language (shortcut method)
     *
     * @return \Blaspsoft\Blasp\BlaspService
     */
    public static function english(): BlaspService
    {
        return static::getFacadeRoot()->english();
    }

    /**
     * Set Spanish language (shortcut method)
     *
     * @return \Blaspsoft\Blasp\BlaspService
     */
    public static function spanish(): BlaspService
    {
        return static::getFacadeRoot()->spanish();
    }

    /**
     * Set German language (shortcut method)
     *
     * @return \Blaspsoft\Blasp\BlaspService
     */
    public static function german(): BlaspService
    {
        return static::getFacadeRoot()->german();
    }

    /**
     * Set French language (shortcut method)
     *
     * @return \Blaspsoft\Blasp\BlaspService
     */
    public static function french(): BlaspService
    {
        return static::getFacadeRoot()->french();
    }

    /**
     * Enable checking against all available languages
     *
     * @return \Blaspsoft\Blasp\BlaspService
     */
    public static function allLanguages(): BlaspService
    {
        return static::getFacadeRoot()->allLanguages();
    }

    /**
     * Set custom mask character for censoring profanities
     *
     * @param string $character
     * @return \Blaspsoft\Blasp\BlaspService
     */
    public static function maskWith(string $character): BlaspService
    {
        return static::getFacadeRoot()->maskWith($character);
    }

    /**
     * Check text for profanity (backwards compatible)
     *
     * @param string|null $string
     * @return \Blaspsoft\Blasp\BlaspService
     */
    public static function check(?string $string): BlaspService
    {
        return static::getFacadeRoot()->check($string);
    }
}
