<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Core\Dictionary;
use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;

class SubstitutionsAppendTest extends TestCase
{
    public function test_substitutions_append_are_merged_into_the_table(): void
    {
        $this->assertFalse(Blasp::check('fvck')->isOffensive());

        Config::set('blasp.substitutions_append', ['/u/' => ['v']]);

        $this->assertTrue(Blasp::check('fvck')->isOffensive());
        $this->assertContains('u', Dictionary::forLanguage('english')->getSubstitutions()['/u/']);
        $this->assertContains('v', Dictionary::forLanguage('english')->getSubstitutions()['/u/']);
    }

    public function test_substitutions_append_apply_to_multi_language_checks(): void
    {
        Config::set('blasp.substitutions_append', ['/u/' => ['v']]);

        $this->assertTrue(Blasp::allLanguages()->check('fvck')->isOffensive());
    }

    public function test_merge_substitutions_adds_per_letter_without_duplicates(): void
    {
        $merged = Dictionary::mergeSubstitutions(
            ['/a/' => ['a', '4'], '/b/' => ['b']],
            ['/a/' => ['4', '@'], '/c/' => ['c', '('], '/d/' => 'not-a-list']
        );

        $this->assertSame(['a', '4', '@'], $merged['/a/']);
        $this->assertSame(['b'], $merged['/b/']);
        $this->assertSame(['c', '('], $merged['/c/']);
        $this->assertArrayNotHasKey('/d/', $merged);
    }

    public function test_default_table_is_unchanged_when_nothing_is_appended(): void
    {
        $this->assertSame(
            config('blasp.substitutions'),
            Dictionary::mergeSubstitutions(config('blasp.substitutions'), [])
        );
    }
}
