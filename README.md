<p align="center">
    <img src="./assets/icon.png" alt="Blasp Icon" width="150" height="150"/>
    <p align="center">
        <a href="https://github.com/Blaspsoft/blasp/actions/workflows/main.yml"><img alt="GitHub Workflow Status (main)" src="https://github.com/Blaspsoft/blasp/actions/workflows/main.yml/badge.svg"></a>
        <a href="https://packagist.org/packages/blaspsoft/blasp"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/blaspsoft/blasp"></a>
        <a href="https://packagist.org/packages/blaspsoft/blasp"><img alt="Latest Version" src="https://img.shields.io/packagist/v/blaspsoft/blasp"></a>
        <a href="https://packagist.org/packages/blaspsoft/blasp"><img alt="License" src="https://img.shields.io/packagist/l/blaspsoft/blasp"></a>
    </p>
</p>

# Blasp - Profanity Filter for Laravel

Blasp is a profanity filter package for Laravel that helps detect and mask profane words in a given sentence. It offers a robust set of features for handling variations of offensive language, including substitutions, obscured characters, and doubled letters.

## Installation

You can install the package via Composer:

```bash
composer require blaspsoft/blasp
```

## Usage

### Basic Usage

To use the profanity filter, simply call the `Blasp::check()` method with the sentence you want to check for profanity.

```php
use Blaspsoft\Blasp\Facades\Blasp;

$sentence = 'This is a fucking shit sentence';
$check = Blasp::check($sentence);
```

The returned object will contain the following properties:

- **sourceString**: The original string you passed.
- **cleanString**: The string with profanities masked (e.g., replaced with `*`).
- **hasProfanity**: A boolean indicating whether the string contains profanity.
- **profanitiesCount**: The number of profanities found.
- **uniqueProfanitiesFound**: An array of unique profanities found in the string.

### Example

```php
$sentence = 'This is a fucking shit sentence';
$blasp = Blasp::check($sentence);

$blasp->getSourceString();       // "This is a fucking shit sentence"
$blasp->getCleanString();        // "This is a ******* **** sentence"
$blasp->hasProfanity();       // true
$blasp->getProfanitiesCount();   // 2
$blasp->getUniqueProfanitiesFound(); // ['fucking', 'shit']
```

### Profanity Detection Types

Blasp can detect different types of profanities based on variations such as:

1. **Straight match**: Direct matches of profane words.
2. **Substitution**: Substituted characters (e.g., `pro0fán1ty`).
3. **Obscured**: Profanities with separators (e.g., `p-r-o-f-a-n-i-t-y`).
4. **Doubled**: Repeated letters (e.g., `pprrooffaanniittyy`).
5. **Combination**: Combinations of the above (e.g., `pp-rof@n|tty`).

### Laravel Validation Rule

Blasp also provides a custom Laravel validation rule called `blasp_check`, which you can use to validate form input for profanity.

#### Example

```php
$request->merge(['sentence' => 'This is f u c k 1 n g awesome!']);

$validated = $request->validate([
    'sentence' => ['blasp_check'],
]);
```

### Configuration

Blasp uses a configuration file (`config/blasp.php`) to manage the list of profanities, separators, and substitutions. You can publish the configuration file using the following Artisan command:

```bash
php artisan vendor:publish --tag="blasp-config"
```

### Custom Configuration

You can specify custom profanity and false positive lists using the `configure()` method:

```php
use Blaspsoft\Blasp\Facades\Blasp;

$blasp = Blasp::configure(
    profanities: $your_custom_profanities,
    falsePositives: $your_custom_false_positives
)->check($text);
```

This is particularly useful when you need different profanity rules for specific contexts, such as username validation.

### Cache Management

Blasp uses Laravel's cache system to improve performance. The package automatically caches profanity expressions and their variations. To clear the cache, you can use the provided Artisan command:

```bash
php artisan blasp:clear
```

This command will clear all cached Blasp expressions and configurations.

### Cache Driver Configuration

By default, Blasp uses Laravel's default cache driver. You can customize the cache driver by setting the `cache_driver` option in your configuration file:

```php
// config/blasp.php
return [
    // ... other configuration options ...

    /*
    |--------------------------------------------------------------------------
    | Cache Driver
    |--------------------------------------------------------------------------
    |
    | Specify the cache driver to use for storing profanity expressions.
    | If not specified, the default Laravel cache driver will be used.
    |
    */
    'cache_driver' => env('BLASP_CACHE_DRIVER', null),
];
```

You can also set the cache driver using an environment variable in your `.env` file:

```
BLASP_CACHE_DRIVER=redis
```

This is useful when you want to use a specific cache driver for Blasp, such as Redis for better performance in high-traffic applications.

## License

Blasp is open-sourced software licensed under the [MIT license](LICENSE).
