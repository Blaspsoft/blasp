<p align="center">
    <img src="./assets/icon.png" alt="Blasp Icon" width="150" height="150"/>
</p>

<p align="center">
    <a href="https://github.com/Blaspsoft/blasp/actions/workflows/main.yml"><img alt="GitHub Workflow Status (main)" src="https://github.com/Blaspsoft/blasp/actions/workflows/main.yml/badge.svg"></a>
    <a href="https://packagist.org/packages/blaspsoft/blasp"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/blaspsoft/blasp"></a>
    <a href="https://packagist.org/packages/blaspsoft/blasp"><img alt="Latest Version" src="https://img.shields.io/packagist/v/blaspsoft/blasp"></a>
    <a href="https://packagist.org/packages/blaspsoft/blasp"><img alt="License" src="https://img.shields.io/packagist/l/blaspsoft/blasp"></a>
</p>

# Blasp - Advanced Profanity Filter for Laravel

Blasp is a powerful, extensible profanity filter for Laravel. Version 4 is a ground-up rewrite with a driver-based architecture, severity scoring, masking strategies, Eloquent model integration, and a clean fluent API.

## Features

- **Driver Architecture** — `regex` (detects obfuscation, substitutions, separators), `pattern` (fast exact matching), `phonetic` (catches sound-alike evasions), or `pipeline` (chains multiple drivers together). Extend with custom drivers.
- **Multi-Language** — English, Spanish, German, French with language-specific normalizers. Check one, many, or all at once.
- **Severity Scoring** — Words categorised as mild/moderate/high/extreme. Filter by minimum severity and get a 0-100 score.
- **Masking Strategies** — Character mask (`*`, `#`), grawlix (`!@#$%`), or a custom callback.
- **Eloquent Integration** — `Blaspable` trait auto-sanitizes or rejects profanity on model save.
- **Middleware** — Reject or sanitize profane request fields with configurable severity.
- **Validation Rules** — Fluent validation rule with language, severity, and score threshold support.
- **Testing Utilities** — `Blasp::fake()` for test doubles with assertions.
- **Events** — `ProfanityDetected`, `ContentBlocked`, and `ModelProfanityDetected`.

## Requirements

- PHP 8.2+
- Laravel 8.0+

## Installation

```bash
composer require blaspsoft/blasp
```

Publish configuration:

```bash
# Everything (config + language files)
php artisan vendor:publish --tag="blasp"

# Config only
php artisan vendor:publish --tag="blasp-config"

# Language files only
php artisan vendor:publish --tag="blasp-languages"
```

## Quick Start

```php
use Blaspsoft\Blasp\Facades\Blasp;

$result = Blasp::check('This is a fucking sentence');

$result->isOffensive();  // true
$result->clean();         // "This is a ******* sentence"
$result->original();      // "This is a fucking sentence"
$result->score();         // 30
$result->count();         // 1
$result->uniqueWords();   // ['fucking']
$result->severity();      // Severity::High
```

## Fluent API

All builder methods return a `PendingCheck` and can be chained:

```php
// Language selection
Blasp::in('spanish')->check($text);
Blasp::in('english', 'french')->check($text);
Blasp::inAllLanguages()->check($text);

// Language shortcuts
Blasp::english()->check($text);
Blasp::spanish()->check($text);
Blasp::german()->check($text);
Blasp::french()->check($text);

// Driver selection
Blasp::driver('regex')->check($text);     // Full obfuscation detection (default)
Blasp::driver('pattern')->check($text);   // Fast exact matching
Blasp::driver('phonetic')->check($text);  // Sound-alike detection (e.g. "phuck", "sheit")
Blasp::driver('pipeline')->check($text);  // Chain multiple drivers (config-based)

// Ad-hoc pipeline — chain any drivers without config
Blasp::pipeline('regex', 'phonetic')->check($text);
Blasp::pipeline('pattern', 'phonetic')->in('english')->mask('#')->check($text);

// Shorthand modes
Blasp::strict()->check($text);   // Forces regex driver
Blasp::lenient()->check($text);  // Forces pattern driver

// Masking
Blasp::mask('*')->check($text);        // Character mask (default)
Blasp::mask('#')->check($text);        // Custom character
Blasp::mask('grawlix')->check($text);  // !@#$% cycling
Blasp::mask(fn($word, $len) => '[CENSORED]')->check($text);  // Callback

// Severity filtering
use Blaspsoft\Blasp\Enums\Severity;
Blasp::withSeverity(Severity::High)->check($text);  // Ignores mild/moderate

// Allow/block lists (merged with config)
Blasp::allow('damn', 'hell')->check($text);
Blasp::block('customword')->check($text);

// Chain everything
Blasp::spanish()
    ->mask('#')
    ->withSeverity(Severity::Moderate)
    ->check($text);

// Batch checking
$results = Blasp::checkMany(['text one', 'text two']);
```

## Result Object

The `Result` object is returned by every `check()` call:

| Method | Returns | Description |
|--------|---------|-------------|
| `isOffensive()` | `bool` | Text contains profanity |
| `isClean()` | `bool` | Text is clean |
| `clean()` | `string` | Text with profanities masked |
| `original()` | `string` | Original unmodified text |
| `score()` | `int` | Severity score (0-100) |
| `count()` | `int` | Total profanity matches |
| `uniqueWords()` | `array` | Unique base words detected |
| `severity()` | `?Severity` | Highest severity in matches |
| `words()` | `Collection` | `MatchedWord` objects with position, length, severity |
| `toArray()` | `array` | Full result as array |
| `toJson()` | `string` | Full result as JSON |

`Result` implements `JsonSerializable`, `Stringable` (returns clean text), and `Countable`.

## Detection Types

The regex driver detects obfuscated profanity:

| Type | Example | Detected As |
|------|---------|-------------|
| Straight match | `fucking` | `fucking` |
| Substitution | `fÛck!ng`, `f4ck` | `fucking`, `fuck` |
| Separators | `f-u-c-k-i-n-g`, `f@ck` | `fucking`, `fuck` |
| Doubled | `ffuucckkiinngg` | `fucking` |
| Combination | `f-uuck!ng` | `fucking` |

> **Separator limit:** The regex driver allows up to 3 separator characters between each letter (e.g., `f--u--c--k`). This covers all realistic obfuscation patterns while keeping regex complexity low enough for PHP-FPM environments.

The pattern driver only detects straight word-boundary matches.

The phonetic driver uses `metaphone()` + Levenshtein distance to catch words that *sound like* profanity but are spelled differently:

| Type | Example | Detected As |
|------|---------|-------------|
| Phonetic spelling | `phuck` | `fuck` |
| Shortened form | `fuk` | `fuck` |
| Sound-alike | `sheit` | `shit` |

Configure sensitivity in `config/blasp.php` under `drivers.phonetic`. A curated false-positive list prevents common words like "fork", "duck", and "beach" from being flagged.

### Pipeline Driver

The pipeline driver chains multiple drivers together so a single `check()` call runs all of them. It uses **union merge** semantics — text is flagged if **any** driver finds a match.

```php
// Config-based: set 'default' => 'pipeline' or use driver('pipeline')
Blasp::driver('pipeline')->check('phuck this sh1t');

// Ad-hoc: pick drivers on the fly (no config needed)
Blasp::pipeline('regex', 'phonetic')->check('phuck this sh1t');
Blasp::pipeline('regex', 'pattern', 'phonetic')->check($text);
```

When multiple drivers detect the same word at the same position, duplicates are removed — only the longest match is kept. Masks are applied from the merged result, and the score is recalculated across all matches.

Configure the default sub-drivers in `config/blasp.php`:

```php
'drivers' => [
    'pipeline' => [
        'drivers' => ['regex', 'phonetic'],  // Drivers to chain
    ],
],
```

## Eloquent Integration

The `Blaspable` trait automatically checks model attributes during save:

```php
use Blaspsoft\Blasp\Blaspable;

class Comment extends Model
{
    use Blaspable;

    protected array $blaspable = ['body', 'title'];
}
```

```php
// Sanitize mode (default) — profanity is masked, model saves
$comment = Comment::create(['body' => 'This is fucking great']);
$comment->body; // "This is ******* great"

// Check what happened
$comment->hadProfanity();            // true
$comment->blaspResults();            // ['body' => Result, 'title' => Result]
$comment->blaspResult('body');       // Result instance
```

### Per-Model Overrides

```php
class Comment extends Model
{
    use Blaspable;

    protected array $blaspable = ['body', 'title'];
    protected string $blaspMode = 'reject';     // 'sanitize' (default) | 'reject'
    protected string $blaspLanguage = 'spanish'; // null = config default
    protected string $blaspMask = '#';           // null = config default
}
```

### Reject Mode

In reject mode, saving a model with profanity throws `ProfanityRejectedException` and the model is not persisted:

```php
use Blaspsoft\Blasp\Exceptions\ProfanityRejectedException;

try {
    $comment = Comment::create(['body' => 'profane text']);
} catch (ProfanityRejectedException $e) {
    $e->attribute; // 'body'
    $e->result;    // Result instance
    $e->model;     // The unsaved model
}
```

### Disabling Checking

```php
Comment::withoutBlaspChecking(function () {
    Comment::create(['body' => 'unchecked content']);
});
```

### Events

A `ModelProfanityDetected` event fires whenever profanity is detected on a model attribute (both sanitize and reject modes):

```php
use Blaspsoft\Blasp\Events\ModelProfanityDetected;

Event::listen(ModelProfanityDetected::class, function ($event) {
    $event->model;     // The model instance
    $event->attribute; // Which attribute had profanity
    $event->result;    // Result instance
});
```

## Middleware

Use `CheckProfanity` to filter incoming request fields. A `blasp` middleware alias is registered automatically:

```php
// Using the short alias (recommended)
Route::post('/comment', CommentController::class)
    ->middleware('blasp');

// With parameters: action, severity
Route::post('/comment', CommentController::class)
    ->middleware('blasp:sanitize,mild');

// Or using the class directly
use Blaspsoft\Blasp\Middleware\CheckProfanity;

Route::post('/comment', CommentController::class)
    ->middleware(CheckProfanity::class);
```

| Action | Behaviour |
|--------|-----------|
| `reject` (default) | Returns 422 JSON with field errors |
| `sanitize` | Replaces profane fields in the request and continues |

Configure which fields to check in `config/blasp.php`:

```php
'middleware' => [
    'action' => 'reject',
    'fields' => ['*'],                            // '*' = all fields
    'except' => ['password', 'email', '_token'],  // Always skipped
    'severity' => 'mild',
],
```

## Validation Rules

### String Rule

```php
$request->validate([
    'comment' => ['required', 'blasp_check'],
    'bio'     => ['required', 'blasp_check:spanish'],
]);
```

### Fluent Rule Object

```php
use Blaspsoft\Blasp\Rules\Profanity;
use Blaspsoft\Blasp\Enums\Severity;

$request->validate([
    'comment' => ['required', Profanity::in('english')],
    'bio'     => ['required', Profanity::severity(Severity::High)],
    'tagline' => ['required', Profanity::maxScore(50)],
]);
```

## Blade Directive

The `@clean` directive sanitizes and escapes text for safe display in views:

```blade
<p>@clean($comment->body)</p>

{{-- Equivalent to: {{ app('blasp')->check($comment->body)->clean() }} --}}
```

Output is HTML-escaped via `e()` for XSS safety.

## Str / Stringable Macros

Blasp registers macros on Laravel's `Str` and `Stringable` classes:

```php
use Illuminate\Support\Str;

// Static methods
Str::isProfane('fuck this');        // true
Str::isProfane('hello');            // false
Str::cleanProfanity('fuck this');   // '**** this'
Str::cleanProfanity('hello');       // 'hello'

// Fluent Stringable methods
Str::of('fuck this')->isProfane();          // true
Str::of('fuck this')->cleanProfanity();     // Stringable('**** this')
Str::of('hello')->cleanProfanity()->upper(); // 'HELLO' (chaining works)
```

## Configuration

Full `config/blasp.php` reference:

```php
return [
    'default'   => env('BLASP_DRIVER', 'regex'),       // 'regex' | 'pattern' | 'phonetic' | 'pipeline'
    'language'  => env('BLASP_LANGUAGE', 'english'),    // Default language
    'mask'      => '*',                                 // Default mask character
    'severity'  => 'mild',                              // Minimum severity
    'events'    => false,                               // Fire ProfanityDetected events

    'cache' => [
        'enabled' => true,
        'driver'  => env('BLASP_CACHE_DRIVER'),
        'ttl'     => 86400,
        'results' => true,          // Cache check() results by content hash
    ],

    'middleware' => [
        'action'   => 'reject',
        'fields'   => ['*'],
        'except'   => ['password', 'email', '_token'],
        'severity' => 'mild',
    ],

    'model' => [
        'mode' => env('BLASP_MODEL_MODE', 'sanitize'),  // 'sanitize' | 'reject'
    ],

    'drivers' => [
        'pipeline' => [
            'drivers' => ['regex', 'phonetic'],    // Sub-drivers to chain
        ],
        'phonetic' => [
            'phonemes' => 4,                       // metaphone code length (2-8)
            'min_word_length' => 3,                // skip short words
            'max_distance_ratio' => 0.6,           // levenshtein threshold (0.3-0.8)
            'supported_languages' => ['english'],  // metaphone is English-oriented
            'false_positives' => ['fork', '...'],  // never flag these words
        ],
    ],

    'allow'  => [],    // Global allow-list
    'block'  => [],    // Global block-list

    'separators'      => [...],  // Characters treated as separators
    'substitutions'   => [...],  // Character leet-speak mappings
    'false_positives' => [...],  // Words that should never be flagged
];
```

## Custom Drivers

Implement `DriverInterface` and register with the manager:

```php
use Blaspsoft\Blasp\Core\Contracts\DriverInterface;
use Blaspsoft\Blasp\Core\Result;
use Blaspsoft\Blasp\Core\Dictionary;
use Blaspsoft\Blasp\Core\Contracts\MaskStrategyInterface;

class MyDriver implements DriverInterface
{
    public function detect(string $text, Dictionary $dictionary, MaskStrategyInterface $mask, array $options = []): Result
    {
        // Your detection logic
    }
}

// Register in a service provider
Blasp::extend('my-driver', fn($app) => new MyDriver());

// Use it
Blasp::driver('my-driver')->check($text);
```

## Caching

Blasp caches `check()` results by default. When the same text is checked with the same configuration (language, driver, severity, allow/block lists), the cached result is returned instantly.

```php
// First call — runs full analysis, caches result
$result = Blasp::check('some text');

// Second call — returns cached result
$result = Blasp::check('some text');
```

Configure caching in `config/blasp.php`:

```php
'cache' => [
    'enabled' => true,                      // Master switch for all caching
    'driver'  => env('BLASP_CACHE_DRIVER'), // null = default cache driver
    'ttl'     => 86400,                     // Cache lifetime in seconds
    'results' => true,                      // Cache check() results (disable independently)
],
```

Result caching is automatically bypassed when using a `CallbackMask` (closures can't be serialized). Clear both dictionary and result caches with:

```bash
php artisan blasp:clear
```

Or programmatically:

```php
Dictionary::clearCache();
```

## Artisan Commands

```bash
# Clear the profanity cache
php artisan blasp:clear

# Test text from the command line
php artisan blasp:test "some text to check" --lang=english --detail

# List available languages with word counts
php artisan blasp:languages
```

## Testing

### Faking

```php
use Blaspsoft\Blasp\Facades\Blasp;
use Blaspsoft\Blasp\Core\Result;

// Replace with a fake — all checks return clean by default
Blasp::fake();

// Pre-configure specific responses
Blasp::fake([
    'bad text'   => Result::withMatches(['fuck']),
    'clean text' => Result::none('clean text'),
]);

$result = Blasp::check('bad text');
$result->isOffensive(); // true

// Assertions
Blasp::assertChecked();
Blasp::assertCheckedTimes(1);
Blasp::assertCheckedWith('bad text');
```

### Disabling Filtering

```php
Blasp::withoutFiltering(function () {
    // All checks return clean results
});
```

## Events

Enable global events with `'events' => true` in config:

| Event | Fired When | Properties |
|-------|------------|------------|
| `ProfanityDetected` | `check()` finds profanity | `result`, `originalText` |
| `ContentBlocked` | Middleware detects profanity | `result`, `request`, `field`, `action` |
| `ModelProfanityDetected` | Blaspable trait detects profanity | `model`, `attribute`, `result` |

`ModelProfanityDetected` always fires (not gated by the `events` config).

## Migrating from v3

### Namespace Changes

| v3 | v4 |
|----|-----|
| `Blaspsoft\Blasp\Facades\Blasp` | `Blaspsoft\Blasp\Facades\Blasp` (unchanged) |
| `Blaspsoft\Blasp\ServiceProvider` | `Blaspsoft\Blasp\BlaspServiceProvider` |

The Laravel auto-discovery handles provider/alias registration automatically. The facade namespace is the same as v3, so no import changes are needed for the facade.

### Config Changes

| v3 Key | v4 Key | Notes |
|--------|--------|-------|
| `default_language` | `language` | `default_language` still works as alias |
| `mask_character` | `mask` | `mask_character` still works as alias |
| `cache_driver` | `cache.driver` | `cache_driver` still works as alias |
| — | `default` | New: driver selection (`regex`/`pattern`) |
| — | `severity` | New: minimum severity level |
| — | `events` | New: enable global events |
| — | `allow` / `block` | New: global allow/block lists |
| — | `middleware` | New: middleware configuration section |
| — | `model` | New: Blaspable trait configuration |

### Result API Changes

| v3 Method | v4 Method |
|-----------|-----------|
| `hasProfanity()` | `isOffensive()` |
| `getCleanString()` | `clean()` |
| `getSourceString()` | `original()` |
| `getProfanitiesCount()` | `count()` |
| `getUniqueProfanitiesFound()` | `uniqueWords()` |

All v3 methods still work as deprecated aliases.

### Builder API Changes

| v3 Method | v4 Method |
|-----------|-----------|
| `maskWith($char)` | `mask($char)` |
| `allLanguages()` | `inAllLanguages()` |
| `language($lang)` | `in($lang)` |
| `configure($profanities, $falsePositives)` | `block(...$words)` / `allow(...$words)` |

All v3 methods still work as deprecated aliases.

### New in v4

- **Driver architecture** — `regex` and `pattern` drivers, custom driver support
- **Severity system** — Mild/Moderate/High/Extreme levels with scoring
- **Masking strategies** — Grawlix and callback masking
- **Blaspable trait** — Automatic Eloquent model profanity checking
- **Middleware** — Request-level profanity filtering
- **Fluent validation rule** — `Profanity::in('spanish')->severity(Severity::High)`
- **Testing utilities** — `Blasp::fake()`, assertions, `withoutFiltering()`
- **Events** — `ProfanityDetected`, `ContentBlocked`, `ModelProfanityDetected`
- **Artisan commands** — `blasp:clear`, `blasp:test`, `blasp:languages`
- **Batch checking** — `Blasp::checkMany([...])`
- **Multi-language in one call** — `Blasp::in('english', 'spanish')->check($text)`

## Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## License

Blasp is open-sourced software licensed under the [MIT license](LICENSE).
