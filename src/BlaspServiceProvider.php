<?php

namespace Blaspsoft\Blasp;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
class BlaspServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/blasp.php' => config_path('blasp.php'),
            ], 'blasp-config');

            $this->publishes([
                __DIR__ . '/../config/languages' => config_path('languages'),
            ], 'blasp-languages');

            $this->publishes([
                __DIR__ . '/../config/blasp.php' => config_path('blasp.php'),
                __DIR__ . '/../config/languages' => config_path('languages'),
            ], 'blasp');

            $this->commands([
                Console\ClearCommand::class,
                Console\TestCommand::class,
                Console\LanguagesCommand::class,
            ]);
        }

        $this->registerValidationRule();
        $this->registerMiddlewareAlias();
        $this->registerBladeDirectives();
        $this->registerStringMacros();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/blasp.php', 'blasp');

        $this->app->singleton('blasp', function ($app) {
            return new BlaspManager($app);
        });

        $this->app->alias('blasp', BlaspManager::class);
    }

    protected function registerValidationRule(): void
    {
        $this->app['validator']->extend('blasp_check', function ($attribute, $value, $parameters) {
            if (!is_string($value) || $value === '') {
                return true;
            }

            $language = $parameters[0] ?? config('blasp.language', config('blasp.default_language', 'english'));

            $manager = $this->app->make('blasp');

            $result = $manager->in($language)->check($value);

            return !$result->isOffensive();
        }, 'The :attribute contains profanity.');
    }

    protected function registerMiddlewareAlias(): void
    {
        $this->app['router']->aliasMiddleware('blasp', Middleware\CheckProfanity::class);
    }

    protected function registerBladeDirectives(): void
    {
        Blade::directive('clean', function (string $expression) {
            return "<?php echo e(app('blasp')->check({$expression})->clean()); ?>";
        });
    }

    protected function registerStringMacros(): void
    {
        Str::macro('isProfane', function (string $text): bool {
            return app('blasp')->check($text)->isOffensive();
        });

        Str::macro('cleanProfanity', function (string $text): string {
            return app('blasp')->check($text)->clean();
        });

        Stringable::macro('isProfane', function (): bool {
            return app('blasp')->check((string) $this)->isOffensive();
        });

        Stringable::macro('cleanProfanity', function (): Stringable {
            return new Stringable(app('blasp')->check((string) $this)->clean());
        });
    }
}
