<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;

class MatchEmbeddedTest extends TestCase
{
    public static function embeddedProvider(): array
    {
        return [
            'suffix embedded' => ['myshit'],
            'mixed case embedded' => ['aXfUcKz'],
            'surrounded' => ['qcuntz'],
            'compound with pronoun' => ['fuckher'],
        ];
    }

    #[DataProvider('embeddedProvider')]
    public function test_embedded_profanity_is_skipped_by_default(string $text)
    {
        $this->assertFalse(Blasp::check($text)->isOffensive());
    }

    #[DataProvider('embeddedProvider')]
    public function test_embedded_profanity_is_flagged_when_enabled(string $text)
    {
        $this->assertTrue(Blasp::matchEmbedded()->check($text)->isOffensive());
    }

    #[DataProvider('embeddedProvider')]
    public function test_config_enables_embedded_matching(string $text)
    {
        Config::set('blasp.match_embedded', true);

        $this->assertTrue(Blasp::check($text)->isOffensive());
    }

    public function test_method_overrides_config()
    {
        Config::set('blasp.match_embedded', true);

        $this->assertFalse(Blasp::matchEmbedded(false)->check('myshit')->isOffensive());
    }

    public function test_false_positives_still_apply_when_enabled()
    {
        foreach (['classroom', 'assignment', 'passion', 'association'] as $word) {
            $this->assertFalse(
                Blasp::matchEmbedded()->check($word)->isOffensive(),
                "\"$word\" should stay clean with matchEmbedded()"
            );
        }
    }

    public function test_allow_list_still_applies_when_enabled()
    {
        $this->assertFalse(Blasp::matchEmbedded()->allow('shit')->check('myshit')->isOffensive());
    }

    public function test_reports_the_embedded_word()
    {
        $this->assertSame(['shit'], Blasp::matchEmbedded()->check('myshit')->uniqueWords());
    }

    public function test_pipeline_passes_option_to_regex_driver()
    {
        $this->assertTrue(Blasp::pipeline('regex', 'phonetic')->matchEmbedded()->check('myshit')->isOffensive());
    }

    public function test_cached_results_are_kept_separate()
    {
        Config::set('blasp.cache.enabled', true);

        $this->assertFalse(Blasp::check('myshit')->isOffensive());
        $this->assertTrue(Blasp::matchEmbedded()->check('myshit')->isOffensive());
        $this->assertFalse(Blasp::check('myshit')->isOffensive());
    }
}
