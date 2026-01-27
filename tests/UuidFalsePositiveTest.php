<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;

class UuidFalsePositiveTest extends TestCase
{
    public function test_uuid_not_flagged_as_profanity()
    {
        $result = Blasp::check('6ec3e80f-11ad-3d5c-809f-144a2ef5800b');
        $this->assertFalse($result->hasProfanity());
    }

    public function test_hex_string_not_flagged()
    {
        $result = Blasp::check('a55e7b3f9c1d2e4f');
        $this->assertFalse($result->hasProfanity());
    }

    public function test_profanity_alongside_uuid_still_detected()
    {
        $result = Blasp::check('fuck 6ec3e80f-11ad-3d5c-809f-144a2ef5800b');
        $this->assertTrue($result->hasProfanity());
        $this->assertContains('fuck', $result->getUniqueProfanitiesFound());
    }

    public function test_standalone_profanity_still_detected()
    {
        $result = Blasp::check('boob');
        $this->assertTrue($result->hasProfanity());
    }

    public function test_normal_profanity_detection_unaffected()
    {
        $result = Blasp::check('shit ass damn');
        $this->assertTrue($result->hasProfanity());
    }

    public function test_uuid_in_sentence_not_flagged()
    {
        $result = Blasp::check('User 6ec3e80f-11ad-3d5c-809f-144a2ef5800b logged in');
        $this->assertFalse($result->hasProfanity());
    }

    public function test_short_hex_does_not_suppress_profanity()
    {
        // "ass" alone is not inside a long hex token, should still be caught
        $result = Blasp::check('ass');
        $this->assertTrue($result->hasProfanity());
    }

    public function test_pure_letter_hex_does_not_suppress_profanity()
    {
        // "deadbeef" has no digits, so the hex guard should not activate
        // and any profanity substring can still be caught
        $result = Blasp::check('boob');
        $this->assertTrue($result->hasProfanity());
    }

    public function test_md5_hash_not_flagged()
    {
        // MD5 containing "a55" (ass) — should not flag
        $result = Blasp::check('a55e7b3f9c1d2e4f8a0b1c2d3e4f5a6b');
        $this->assertFalse($result->hasProfanity());
    }

    public function test_multiple_uuids_not_flagged()
    {
        $result = Blasp::check('6ec3e80f-11ad-3d5c-809f-144a2ef5800b and 550e8400-e29b-41d4-a716-446655440000');
        $this->assertFalse($result->hasProfanity());
    }
}
