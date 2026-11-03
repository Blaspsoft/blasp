<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Core\Dictionary;
use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;

class SafeWordsTest extends TestCase
{
    private ?string $languageFile = null;

    private bool $createdLanguageDirectory = false;

    protected function tearDown(): void
    {
        if ($this->languageFile !== null && is_file($this->languageFile)) {
            unlink($this->languageFile);
        }

        if ($this->createdLanguageDirectory) {
            $directory = config_path('languages');

            if (is_dir($directory) && glob($directory . '/*') === []) {
                rmdir($directory);
            }
        }

        parent::tearDown();
    }

    public function test_app_config_safe_words_list_is_not_flagged(): void
    {
        $this->assertTrue(Blasp::check('damn')->isOffensive());

        Config::set('blasp.safe_words', ['Damn', 'acme']);

        $clean = Blasp::check('damn');
        $this->assertFalse($clean->isOffensive());
        $this->assertSame('damn', $clean->clean());

        $mixed = Blasp::check('damn this shit');
        $this->assertTrue($mixed->isOffensive());
        $this->assertStringContainsString('damn', $mixed->clean());
        $this->assertStringNotContainsString('shit', $mixed->clean());

        $this->assertFalse(Blasp::check('the acme launch')->isOffensive());
    }

    public function test_safe_words_list_is_merged_into_the_dictionary(): void
    {
        Config::set('blasp.safe_words', ['damn']);

        $dictionary = Dictionary::forLanguage('english');

        $this->assertNotContains('damn', $dictionary->getProfanities());
        $this->assertContains('shit', $dictionary->getProfanities());
        $this->assertContains('damn', $dictionary->getFalsePositives());
    }

    public function test_safe_words_list_applies_to_every_loaded_language(): void
    {
        Config::set('blasp.safe_words', ['mierda']);

        $dictionary = Dictionary::forLanguages(['english', 'spanish']);

        $this->assertNotContains('mierda', array_map('strtolower', $dictionary->getProfanities()));
        $this->assertContains('mierda', array_map('strtolower', $dictionary->getFalsePositives()));
        $this->assertFalse(Blasp::in('spanish')->check('esto es mierda')->isOffensive());
        $this->assertTrue(Blasp::in('spanish')->check('esto es joder')->isOffensive());
    }

    public function test_published_language_file_safe_words_list_is_merged(): void
    {
        $directory = config_path('languages');

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
            $this->createdLanguageDirectory = true;
        }

        $this->languageFile = $directory . '/zzsafewords.php';

        file_put_contents($this->languageFile, <<<'PHP'
<?php

return [
    'profanities' => ['damn', 'shit'],
    'false_positives' => [],
    'safe_words' => ['damn'],
];
PHP);

        $config = Dictionary::loadLanguageConfig('zzsafewords');
        $this->assertContains('damn', $config['safe_words']);

        $dictionary = Dictionary::forLanguage('zzsafewords');

        $this->assertNotContains('damn', $dictionary->getProfanities());
        $this->assertContains('shit', $dictionary->getProfanities());
        $this->assertContains('damn', $dictionary->getFalsePositives());
        $this->assertFalse(Blasp::in('zzsafewords')->check('damn')->isOffensive());
        $this->assertTrue(Blasp::in('zzsafewords')->check('shit')->isOffensive());
    }

    public function test_empty_safe_words_entries_are_ignored(): void
    {
        Config::set('blasp.safe_words', ['', '   ']);

        $dictionary = Dictionary::forLanguage('english');

        $this->assertNotContains('', $dictionary->getFalsePositives());
        $this->assertNotContains('   ', $dictionary->getFalsePositives());
        $this->assertTrue(Blasp::check('damn')->isOffensive());
    }

    public function test_safe_word_containing_a_shorter_profanity_is_not_masked(): void
    {
        $this->assertTrue(Blasp::matchEmbedded()->block('cum')->check('vacuum')->isOffensive());

        Config::set('blasp.safe_words', ['vacuum']);

        $result = Blasp::matchEmbedded()->block('cum')->check('vacuum the cum');

        $this->assertSame('vacuum the ***', $result->clean());
    }

    public function test_changing_safe_words_invalidates_cached_results(): void
    {
        Config::set('blasp.cache.results', true);

        $this->assertTrue(Blasp::check('damn')->isOffensive());

        Config::set('blasp.safe_words', ['damn']);

        $this->assertFalse(Blasp::check('damn')->isOffensive());
    }
}
