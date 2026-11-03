<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Enums\Severity;
use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;

class ConfigKeysHonouredTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('blasp.cache.enabled', false);
    }

    public function test_severity_config_sets_the_default_minimum()
    {
        Config::set('blasp.severity', 'high');

        $result = Blasp::check('damn this shit');

        $this->assertSame(['shit'], $result->uniqueWords());
    }

    public function test_explicit_severity_overrides_config()
    {
        Config::set('blasp.severity', 'extreme');

        $this->assertTrue(Blasp::withSeverity(Severity::Mild)->check('damn')->isOffensive());
    }

    public function test_severity_config_is_case_insensitive()
    {
        Config::set('blasp.severity', 'HIGH');

        $this->assertFalse(Blasp::check('damn')->isOffensive());
    }

    public function test_invalid_severity_config_throws()
    {
        Config::set('blasp.severity', 'severe');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('blasp.severity');

        Blasp::check('damn');
    }

    public function test_default_mild_severity_changes_nothing()
    {
        Config::set('blasp.severity', 'mild');

        $this->assertSame(['damn', 'shit'], Blasp::check('damn this shit')->uniqueWords());
    }

    public function test_global_false_positives_config_is_applied()
    {
        $this->assertTrue(Blasp::check('spicy food')->isOffensive());

        Config::set('blasp.false_positives', ['spicy']);

        $this->assertFalse(Blasp::check('spicy food')->isOffensive());
    }

    public function test_global_false_positives_apply_to_every_language()
    {
        Config::set('blasp.false_positives', ['spicy']);

        $this->assertFalse(Blasp::inAllLanguages()->check('spicy food')->isOffensive());
    }

    public function test_configure_false_positives_are_applied()
    {
        $this->assertFalse(Blasp::configure(null, ['spicy'])->check('spicy food')->isOffensive());
    }
}
