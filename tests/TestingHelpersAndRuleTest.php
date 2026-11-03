<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\BlaspManager;
use Blaspsoft\Blasp\Enums\Severity;
use Blaspsoft\Blasp\Facades\Blasp;
use Blaspsoft\Blasp\Rules\Profanity;
use Blaspsoft\Blasp\Testing\BlaspFake;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class TestingHelpersAndRuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('blasp.cache.enabled', false);
    }

    public function test_without_filtering_turns_filtering_back_on_afterwards()
    {
        $inside = Blasp::withoutFiltering(fn () => Blasp::check('fuck you')->isOffensive());

        $this->assertFalse($inside);
        $this->assertTrue(Blasp::check('fuck you')->isOffensive());
        $this->assertInstanceOf(BlaspManager::class, app('blasp'));
    }

    public function test_without_filtering_restores_an_existing_fake()
    {
        $fake = Blasp::fake();

        Blasp::withoutFiltering(fn () => null);

        $this->assertSame($fake, app('blasp'));
    }

    public function test_middleware_routes_work_after_fake()
    {
        Blasp::fake();
        Route::post('/comment', fn () => 'ok')->middleware('blasp');

        $this->postJson('/comment', ['body' => 'hello'])->assertOk();
    }

    public function test_fake_mask_accepts_a_closure()
    {
        Blasp::fake();

        $this->assertInstanceOf(BlaspFake::class, Blasp::mask(fn () => 'X'));
    }

    public function test_strict_forces_the_regex_driver()
    {
        Config::set('blasp.default', 'pattern');

        $this->assertFalse(Blasp::check('f-u-c-k you')->isOffensive());
        $this->assertTrue(Blasp::strict()->check('f-u-c-k you')->isOffensive());
    }

    public function test_rule_options_work_statically()
    {
        $this->assertInstanceOf(Profanity::class, Profanity::in('spanish'));
        $this->assertInstanceOf(Profanity::class, Profanity::maxScore(50));
        $this->assertInstanceOf(Profanity::class, Profanity::severity(Severity::High));

        $this->assertTrue(Validator::make(['b' => 'mierda'], ['b' => [Profanity::in('spanish')]])->fails());
        $this->assertFalse(Validator::make(['b' => 'damn'], ['b' => [Profanity::severity(Severity::High)]])->fails());
    }

    public function test_rule_options_work_on_an_instance_and_chain()
    {
        $rule = Profanity::make()->in('spanish')->severity(Severity::Mild)->maxScore(100);

        $this->assertInstanceOf(Profanity::class, $rule);
        $this->assertFalse(Validator::make(['b' => 'mierda'], ['b' => [$rule]])->fails());
        $this->assertTrue(Validator::make(['b' => 'mierda'], ['b' => [Profanity::in('spanish')->severity(Severity::Mild)]])->fails());
    }

    public function test_rule_rejects_unknown_option()
    {
        $this->expectException(\BadMethodCallException::class);

        Profanity::nope();
    }
}
