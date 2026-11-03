<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Blaspable;
use Blaspsoft\Blasp\Core\Dictionary;
use Blaspsoft\Blasp\Events\ProfanityDetected;
use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

class CastedBlaspableModel extends Model
{
    use Blaspable;

    protected $table = 'casted_notes';
    protected $guarded = [];
    protected array $blaspable = ['secret', 'meta', 'body'];
    protected $casts = [
        'secret' => 'encrypted',
        'meta' => 'array',
    ];
}

class CacheEventsAndCastsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('blasp.cache.enabled', true);
        Cache::flush();

        Schema::create('casted_notes', function (Blueprint $table) {
            $table->id();
            $table->text('secret')->nullable();
            $table->text('meta')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    public function test_cached_result_is_not_reused_after_block_config_changes()
    {
        $this->assertFalse(Blasp::check('zorbleflap here')->isOffensive());

        Config::set('blasp.block', ['zorbleflap']);

        $this->assertTrue(Blasp::check('zorbleflap here')->isOffensive());
    }

    public function test_cached_result_is_not_reused_after_allow_config_changes()
    {
        $this->assertTrue(Blasp::check('shit')->isOffensive());

        Config::set('blasp.allow', ['shit']);

        $this->assertFalse(Blasp::check('shit')->isOffensive());
    }

    public function test_cached_result_is_not_reused_after_mask_config_changes()
    {
        $this->assertSame('****', Blasp::check('shit')->clean());

        Config::set('blasp.mask', '#');

        $this->assertSame('####', Blasp::check('shit')->clean());
    }

    public function test_cached_result_is_not_reused_after_default_language_changes()
    {
        $this->assertFalse(Blasp::check('mierda')->isOffensive());

        Config::set('blasp.language', 'spanish');

        $this->assertTrue(Blasp::check('mierda')->isOffensive());
    }

    public function test_clear_cache_invalidates_results_no_longer_tracked()
    {
        Config::set('blasp.cache.max_tracked_keys', 1);

        Blasp::check('first text');
        Blasp::check('second text');

        $generation = Dictionary::cacheGeneration();
        Dictionary::clearCache();

        $this->assertSame($generation + 1, Dictionary::cacheGeneration());
    }

    public function test_profanity_detected_event_fires_on_cache_hits()
    {
        Config::set('blasp.events', true);
        Event::fake([ProfanityDetected::class]);

        Blasp::check('fuck this');
        Blasp::check('fuck this');

        Event::assertDispatchedTimes(ProfanityDetected::class, 2);
    }

    public function test_encrypted_attribute_is_checked_as_plain_text()
    {
        $note = CastedBlaspableModel::create(['secret' => 'what the fuck']);

        $this->assertTrue($note->hadProfanity());
        $this->assertSame('what the ****', $note->fresh()->secret);
    }

    public function test_clean_encrypted_attribute_is_saved_unchanged()
    {
        $note = CastedBlaspableModel::create(['secret' => 'hello there']);

        $this->assertFalse($note->hadProfanity());
        $this->assertSame('hello there', $note->fresh()->secret);
    }

    public function test_array_cast_attribute_is_not_corrupted()
    {
        $note = CastedBlaspableModel::create(['meta' => ['note' => 'fuck you'], 'body' => 'hello']);

        $this->assertSame(['note' => 'fuck you'], $note->fresh()->meta);
    }

    public function test_plain_attribute_is_still_sanitized()
    {
        $note = CastedBlaspableModel::create(['body' => 'what the fuck']);

        $this->assertSame('what the ****', $note->fresh()->body);
    }
}
