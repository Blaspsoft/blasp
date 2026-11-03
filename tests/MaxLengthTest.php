<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;
use Blaspsoft\Blasp\Rules\Profanity;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class MaxLengthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('blasp.cache.enabled', false);

        Route::post('/reject', fn () => 'ok')->middleware('blasp');
        Route::post('/sanitize', fn () => 'ok')->middleware('blasp:sanitize');
    }

    public function test_limit_is_off_by_default()
    {
        $this->postJson('/reject', ['body' => str_repeat('hello ', 5000)])->assertOk();
        $this->assertFalse(Validator::make(['b' => str_repeat('hello ', 5000)], ['b' => [Profanity::make()]])->fails());
    }

    public function test_middleware_rejects_over_long_fields_in_both_modes()
    {
        Config::set('blasp.max_length', 100);

        $this->postJson('/reject', ['body' => str_repeat('a', 101)])
            ->assertStatus(422)->assertJsonPath('errors.body.0', 'The body field is too long to check for profanity.');
        $this->postJson('/sanitize', ['body' => str_repeat('a', 101)])->assertStatus(422);
        $this->postJson('/reject', ['body' => str_repeat('a', 100)])->assertOk();
    }

    public function test_rule_fails_over_long_values_from_config()
    {
        Config::set('blasp.max_length', 10);

        $validator = Validator::make(['bio' => 'hello there friend'], ['bio' => [Profanity::make()]]);

        $this->assertTrue($validator->fails());
        $this->assertSame('The bio is too long to check for profanity.', $validator->errors()->first('bio'));
    }

    public function test_rule_max_length_option_overrides_config()
    {
        Config::set('blasp.max_length', 5);

        $this->assertFalse(Validator::make(['b' => 'hello there'], ['b' => [Profanity::maxLength(50)]])->fails());
        $this->assertTrue(Validator::make(['b' => 'hello there'], ['b' => [Profanity::make()->in('english')->maxLength(5)]])->fails());
    }

    public function test_invalid_max_length_config_throws()
    {
        Config::set('blasp.max_length', 'abc');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('blasp.max_length');

        Validator::make(['b' => 'hello'], ['b' => [Profanity::make()]])->fails();
    }

    public function test_numeric_string_max_length_from_env_works()
    {
        Config::set('blasp.max_length', '10');

        $this->assertTrue(Validator::make(['b' => 'hello there friend'], ['b' => [Profanity::make()]])->fails());
    }

    public function test_direct_checks_are_not_limited()
    {
        Config::set('blasp.max_length', 5);

        $this->assertTrue(Blasp::check('this is fucked up')->isOffensive());
    }
}
