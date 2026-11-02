<?php

namespace Blaspsoft\Blasp\Facades;

use Blaspsoft\Blasp\BlaspManager;
use Blaspsoft\Blasp\Core\Result;
use Blaspsoft\Blasp\Enums\Severity;
use Blaspsoft\Blasp\PendingCheck;
use Blaspsoft\Blasp\Testing\BlaspFake;
use Closure;
use Illuminate\Support\Facades\Facade as BaseFacade;

/**
 * @method static Result check(?string $text)
 * @method static array checkMany(array $texts)
 * @method static PendingCheck in(string ...$languages)
 * @method static PendingCheck inAllLanguages()
 * @method static PendingCheck mask(string|Closure $mask)
 * @method static PendingCheck allow(string ...$words)
 * @method static PendingCheck block(string ...$words)
 * @method static PendingCheck withSeverity(Severity $severity)
 * @method static PendingCheck strict()
 * @method static PendingCheck lenient()
 * @method static PendingCheck matchEmbedded(bool $enabled = true)
 * @method static PendingCheck driver(string $driver)
 * @method static PendingCheck pipeline(string ...$drivers)
 * @method static PendingCheck english()
 * @method static PendingCheck spanish()
 * @method static PendingCheck german()
 * @method static PendingCheck french()
 * @method static PendingCheck maskWith(string $character)
 * @method static PendingCheck allLanguages()
 * @method static PendingCheck language(string $language)
 * @method static PendingCheck configure(?array $profanities = null, ?array $falsePositives = null)
 * @method static BlaspManager extend(string $driver, Closure $callback)
 *
 * @see \Blaspsoft\Blasp\BlaspManager
 */
class Blasp extends BaseFacade
{
    protected static function getFacadeAccessor(): string
    {
        return 'blasp';
    }

    public static function fake(array $responses = []): BlaspFake
    {
        $fake = new BlaspFake($responses);
        static::swap($fake);
        return $fake;
    }

    public static function withoutFiltering(Closure $callback): mixed
    {
        $fake = new BlaspFake();
        static::swap($fake);

        try {
            return $callback();
        } finally {
            static::clearResolvedInstance('blasp');
        }
    }

    public static function assertChecked(): void
    {
        $instance = static::getFacadeRoot();
        if (!$instance instanceof BlaspFake) {
            throw new \RuntimeException('Blasp::assertChecked() requires Blasp::fake() to be called first.');
        }
        $instance->assertChecked();
    }

    public static function assertCheckedTimes(int $times): void
    {
        $instance = static::getFacadeRoot();
        if (!$instance instanceof BlaspFake) {
            throw new \RuntimeException('Blasp::assertCheckedTimes() requires Blasp::fake() to be called first.');
        }
        $instance->assertCheckedTimes($times);
    }
}
