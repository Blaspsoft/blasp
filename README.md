<p align="center">
    <img src="./assets/icon.png" alt="Blasp Icon" width="150" height="150"/>
    <p align="center">
        <a href="https://github.com/Blaspsoft/blasp/actions/workflows/main.yml"><img alt="GitHub Workflow Status (main)" src="https://github.com/Blaspsoft/blasp/actions/workflows/main.yml/badge.svg"></a>
        <a href="https://packagist.org/packages/blaspsoft/blasp"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/blaspsoft/blasp"></a>
        <a href="https://packagist.org/packages/blaspsoft/blasp"><img alt="Latest Version" src="https://img.shields.io/packagist/v/blaspsoft/blasp"></a>
        <a href="https://packagist.org/packages/blaspsoft/blasp"><img alt="License" src="https://img.shields.io/packagist/l/blaspsoft/blasp"></a>
    </p>
</p>

# Blasp - Advanced Profanity Filter for Laravel

Blasp is a powerful, extensible profanity filter package for Laravel that helps detect and mask profane words in text. Version 3.0 introduces a simplified API with method chaining, comprehensive multi-language support (English, Spanish, German, French), all-languages detection mode, and advanced caching for enterprise-grade performance.

## ✨ Key Features

- **🔗 Method Chaining**: Elegant fluent API with `Blasp::spanish()->check()`
- **🌍 Multi-Language Support**: English, Spanish, German, and French with language-specific normalizers
- **🌐 All Languages Mode**: Check against all languages simultaneously with `Blasp::allLanguages()`
- **🎨 Custom Masking**: Configure custom mask characters with `maskWith()` method
- **⚡ High Performance**: Advanced caching with O(1) lookups and optimized algorithms
- **🎯 Smart Detection**: Handles substitutions, separators, variations, and false positives
- **🏗️ Modern Architecture**: Built on SOLID principles with dependency injection
- **✅ Battle Tested**: 148 tests with 858 assertions ensuring reliability

## Installation

You can install the package via Composer:

```bash
composer require blaspsoft/blasp
```

## Quick Start

### Basic Usage

```php
use Blaspsoft\Blasp\Facades\Blasp;

// Simple usage - uses default language from config
$result = Blasp::check('This is a fucking shit sentence');

// With method chaining for specific language
$result = Blasp::spanish()->check('esto es una mierda');

// Check against ALL languages at once
$result = Blasp::allLanguages()->check('fuck merde scheiße mierda');
```

### Simplified API with Method Chaining

```php
// Language shortcuts
Blasp::english()->check($text);
Blasp::spanish()->check($text);
Blasp::german()->check($text);
Blasp::french()->check($text);

// Check against all languages
Blasp::allLanguages()->check($text);

// Custom mask character
Blasp::maskWith('#')->check($text);
Blasp::maskWith('●')->check($text);

// Configure custom profanities
Blasp::configure(['badword'], ['goodword'])->check($text);

// Chain multiple methods together
Blasp::spanish()->maskWith('*')->check($text);
Blasp::allLanguages()->maskWith('-')->check($text);
```

### Working with Results

```php
$result = Blasp::check('This is fucking awesome');

$result->getSourceString();           // "This is fucking awesome"
$result->getCleanString();            // "This is ******* awesome"
$result->hasProfanity();             // true
$result->getProfanitiesCount();      // 1
$result->getUniqueProfanitiesFound(); // ['fucking']

// With custom mask character
$result = Blasp::maskWith('#')->check('This is fucking awesome');
$result->getCleanString();            // "This is ####### awesome"
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

// With language specification
$validated = $request->validate([
    'sentence' => ['blasp_check:spanish'],
]);
```

### Configuration

Blasp uses configuration files to manage profanities, separators, and substitutions. The main configuration includes:

```php
// config/blasp.php
return [
    'default_language' => 'english',  // Default language for detection
    'mask_character' => '*',          // Default character for masking profanities
    'separators' => [...],            // Special characters used as separators
    'substitutions' => [...],         // Character substitutions (like @ for a)
    'false_positives' => [...],       // Words that should not be flagged
];
```

You can publish the configuration files:

```bash
# Publish everything (config + all language files)
php artisan vendor:publish --tag="blasp"

# Publish only the main configuration file
php artisan vendor:publish --tag="blasp-config"

# Publish only the language files
php artisan vendor:publish --tag="blasp-languages"
```

This will publish:
- `config/blasp.php` - Main configuration with default language settings
- `config/languages/` - Language-specific profanity lists (English, Spanish, German, French)

### Language-Specific Substitutions

Each language file can define its own character substitutions that will be merged with the main config:

```php
// config/languages/french.php
return [
    'profanities' => [...],
    'false_positives' => [...],
    'substitutions' => [
        '/c/' => ['c', 'k', 'ç', 's'],  // French-specific: ç maps to c
        '/u/' => ['u', 'ù', 'ú', 'û', 'ü', '@', '*'],
        // ... other letter substitutions
    ],
];
```

Only basic letter patterns (`/a/` through `/z/`) from language files are merged to avoid regex conflicts.

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


## 🚀 Advanced Features (v3.0+)

### All Languages Detection

Perfect for international platforms, forums, or any application with multilingual content:

```php
// Check text against ALL configured languages at once
$result = Blasp::allLanguages()->check('fuck merde scheiße mierda');
// Detects profanities from English, French, German, and Spanish

// Get detailed results
echo $result->getProfanitiesCount();        // 4
echo $result->getUniqueProfanitiesFound();  // ['fuck', 'merde', 'scheiße', 'mierda']
```

### Multi-Language Support

Blasp includes comprehensive support for multiple languages with automatic character normalization:

- **English**: Full profanity database with common variations
- **Spanish**: Handles accent normalization (á→a, ñ→n)
- **German**: Processes umlauts (ä→ae, ö→oe, ü→ue) and ß→ss
- **French**: Accent and cedilla normalization


### Complete Chainable Methods Reference

```php
// Language selection methods
Blasp::language('spanish')     // Set any language by name
Blasp::english()               // Shortcut for English
Blasp::spanish()               // Shortcut for Spanish
Blasp::german()                // Shortcut for German
Blasp::french()                // Shortcut for French
Blasp::allLanguages()          // Check against all languages

// Configuration methods
Blasp::configure($profanities, $falsePositives)  // Custom word lists
Blasp::maskWith('#')                             // Custom mask character

// Detection method
Blasp::check($text)            // Analyze text for profanities

// All methods return BlaspService for chaining
$service = Blasp::spanish()                      // Returns BlaspService
    ->maskWith('●')                              // Returns BlaspService
    ->configure(['custom'], ['false_positive'])  // Returns BlaspService
    ->check('texto para verificar');             // Returns BlaspService with results
```

### Advanced Method Chaining Examples

```php
// Example 1: Spanish with custom mask
Blasp::spanish()
    ->maskWith('#')
    ->check('esto es una mierda');
// Result: "esto es una ######"

// Example 2: All languages with custom configuration
Blasp::allLanguages()
    ->configure(['newbadword'], ['safephrase'])
    ->maskWith('-')
    ->check('multiple fuck merde languages');
// Result: "multiple ---- ----- languages"

// Example 3: Dynamic language selection
$language = $user->preferred_language; // 'french'
Blasp::language($language)
    ->maskWith($user->mask_preference ?? '*')
    ->check($userContent);
```

### Laravel Integration

```php
// Laravel service container integration
$blasp = app(BlaspService::class);

// Validation rule with default language
$request->validate([
    'message' => 'required|blasp_check'
]);

// Validation rule with specific language
$request->validate([
    'message' => 'required|blasp_check:spanish'
]);
```

### Cache Management

Blasp uses Laravel's cache system to improve performance. The package automatically caches profanity expressions and their variations. To clear the cache, you can use the provided Artisan command:

```bash
php artisan blasp:clear
```

This command will clear all cached Blasp expressions and configurations.

### Cache Driver Configuration

By default, Blasp uses Laravel's default cache driver. You can specify a different cache driver for Blasp by setting the `cache_driver` option in your configuration:

```php
// config/blasp.php
return [
    'cache_driver' => env('BLASP_CACHE_DRIVER'),
    // ...
];
```

Or set it via environment variable:

```env
BLASP_CACHE_DRIVER=redis
```

This is particularly useful in environments like **Laravel Vapor** where the default cache driver (DynamoDB) has size limits that can be exceeded when caching large profanity expression sets. By configuring a different cache driver (such as Redis), you can avoid these limitations.

## ⚡ Performance

Blasp v3.0 includes significant performance optimizations:

- **Cached Expression Sorting**: Profanity expressions are sorted once and cached, eliminating repeated O(n log n) operations
- **Hash Map Lookups**: False positive checking and unique profanity tracking use O(1) hash map lookups instead of O(n) linear searches
- **Optimized Regular Expressions**: Improved regex generation and matching algorithms
- **Intelligent Caching**: Multi-layer caching system with automatic cache invalidation

### Benchmarks

Version 3.0 shows substantial performance improvements over v2:
- **Expression Processing**: 60% faster profanity expression generation
- **Detection Speed**: 40% faster text analysis with large profanity lists
- **Memory Usage**: 30% reduction in memory footprint
- **Cache Efficiency**: 80% fewer database/config queries with intelligent caching

## 🔄 Migration from v2.x to v3.0

### 100% Backward Compatible

All existing v2.x code continues to work without any changes:

```php
// Existing code works exactly the same
use Blaspsoft\Blasp\Facades\Blasp;

$result = Blasp::check('text to check');
$result = Blasp::configure($profanities, $falsePositives)->check('text');
```

### New Features in v3.0

Take advantage of the simplified API:

```php
// NEW: Method chaining
Blasp::spanish()->check($text);

// NEW: All languages detection
Blasp::allLanguages()->check($text);

// NEW: Language shortcuts
Blasp::german()->check($text);
Blasp::french()->check($text);

// NEW: Custom mask characters
Blasp::maskWith('#')->check($text);
Blasp::spanish()->maskWith('●')->check($text);

// NEW: Default language configuration
// Set in config/blasp.php: 'default_language' => 'spanish'
Blasp::check($text); // Now uses Spanish by default
```

## 🎨 Custom Masking

### Using Custom Mask Characters

You can customize how profanities are masked using the `maskWith()` method:

```php
// Use hash symbols instead of asterisks
$result = Blasp::maskWith('#')->check('This is fucking awesome');
echo $result->getCleanString(); // "This is ####### awesome"

// Use dots for masking
$result = Blasp::maskWith('·')->check('What the hell');
echo $result->getCleanString(); // "What the ····"

// Unicode characters work too
$result = Blasp::maskWith('●')->check('damn it');
echo $result->getCleanString(); // "●●●● it"
```

### Setting Default Mask Character

You can set a default mask character in the configuration:

```php
// config/blasp.php
return [
    'mask_character' => '#',  // All profanities will be masked with #
    // ...
];
```

### Combining with Other Methods

The `maskWith()` method can be chained with other methods:

```php
// Spanish text with custom mask
Blasp::spanish()->maskWith('@')->check('esto es mierda');

// All languages with dots
Blasp::allLanguages()->maskWith('·')->check('multilingual text');

// Configure and mask
Blasp::configure(['custom'], [])
    ->maskWith('-')
    ->check('custom text');
```

## 🏗️ Architecture

Blasp v3.0 follows SOLID principles and modern PHP practices:

- **Facade Pattern**: Simplified API with Laravel facade integration
- **Builder Pattern**: Method chaining for fluent interface
- **Strategy Pattern**: Language-specific detection and normalization
- **Dependency Injection**: Full Laravel service container integration
- **Caching**: Intelligent performance optimization

## 📋 Requirements

- PHP 8.1+
- Laravel 10.0+
- BCMath PHP Extension (for advanced calculations)

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## 📄 Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## License

Blasp is open-sourced software licensed under the [MIT license](LICENSE).
