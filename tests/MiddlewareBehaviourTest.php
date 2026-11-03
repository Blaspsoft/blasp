<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Events\ContentBlocked;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

class MiddlewareBehaviourTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('blasp.cache.enabled', false);

        Route::post('/reject', fn (Request $r) => response()->json($r->all()))->middleware('blasp');
        Route::post('/sanitize', fn (Request $r) => response()->json($r->all()))->middleware('blasp:sanitize');
        Route::post('/high', fn (Request $r) => response()->json($r->all()))->middleware('blasp:reject,high');
        Route::post('/typo', fn () => 'ok')->middleware('blasp:rejekt');
        Route::post('/form-reject', fn () => 'ok')->middleware(['web', 'blasp']);
        Route::post('/bad-severity', fn () => 'ok')->middleware('blasp:reject,severe');
    }

    public function test_flat_json_input_is_rejected_with_unchanged_response()
    {
        $this->postJson('/reject', ['body' => 'fuck you'])
            ->assertStatus(422)
            ->assertExactJson([
                'message' => 'The request contains inappropriate content.',
                'errors' => ['body' => ['The body field contains profanity.']],
            ]);
    }

    public function test_nested_json_input_is_rejected()
    {
        $this->postJson('/reject', ['comment' => ['body' => 'fuck you']])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['comment.body' => ['The comment.body field contains profanity.']]);
    }

    public function test_array_input_is_rejected()
    {
        $this->postJson('/reject', ['tags' => ['nice', 'shit']])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['tags.1']]);
    }

    public function test_every_offending_field_is_reported()
    {
        $this->postJson('/reject', ['title' => 'shit', 'body' => 'fuck'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['title', 'body']]);
    }

    public function test_clean_input_passes()
    {
        $this->postJson('/reject', ['comment' => ['body' => 'hello there']])->assertOk();
    }

    public function test_nested_input_is_sanitized_in_place()
    {
        $this->postJson('/sanitize', ['comment' => ['body' => 'what the fuck', 'id' => 5], 'tags' => ['shit', 'ok']])
            ->assertOk()
            ->assertExactJson(['comment' => ['body' => 'what the ****', 'id' => 5], 'tags' => ['****', 'ok']]);
    }

    public function test_form_posts_are_redirected_back_with_errors()
    {
        $this->from('/form')->post('/form-reject', ['body' => 'fuck you'])
            ->assertRedirect('/form')
            ->assertSessionHasErrors('body');
    }

    public function test_password_fields_are_never_checked()
    {
        $this->postJson('/reject', [
            'password' => 'Shit12345',
            'password_confirmation' => 'Shit12345',
            'current_password' => 'fuckthis1',
            'user' => ['password' => 'Shit12345'],
        ])->assertOk();
    }

    public function test_except_matches_nested_keys_by_name()
    {
        Config::set('blasp.middleware.except', ['notes']);

        $this->postJson('/reject', ['profile' => ['notes' => 'shit happens']])->assertOk();
    }

    public function test_fields_accept_dotted_and_wildcard_patterns()
    {
        Config::set('blasp.middleware.fields', ['user.bio', 'comments.*.body']);

        $this->postJson('/reject', ['user' => ['bio' => 'shit'], 'other' => 'shit'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['user.bio']])->assertJsonMissingPath('errors.other');
        $this->postJson('/reject', ['comments' => [['body' => 'ok'], ['body' => 'fuck']]])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['comments.1.body']]);
        $this->postJson('/reject', ['other' => 'shit'])->assertOk();
    }

    public function test_severity_parameter_is_applied()
    {
        $this->postJson('/high', ['body' => 'damn'])->assertOk();
        $this->postJson('/high', ['body' => 'shit'])->assertStatus(422);
    }

    public function test_invalid_action_throws()
    {
        $this->withoutExceptionHandling();
        $this->expectException(\InvalidArgumentException::class);

        $this->postJson('/typo', ['body' => 'hello']);
    }

    public function test_invalid_severity_throws()
    {
        $this->withoutExceptionHandling();
        $this->expectException(\InvalidArgumentException::class);

        $this->postJson('/bad-severity', ['body' => 'hello']);
    }

    public function test_content_blocked_event_fires_per_field()
    {
        Config::set('blasp.events', true);
        Event::fake([ContentBlocked::class]);

        $this->postJson('/reject', ['title' => 'shit', 'body' => 'fuck']);

        Event::assertDispatchedTimes(ContentBlocked::class, 2);
    }
}
