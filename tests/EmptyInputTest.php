<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\BlaspService;

class EmptyInputTest extends TestCase
{
    protected $blaspService;

    public function setUp(): void
    {
        parent::setUp();
        $this->blaspService = new BlaspService();
    }

    public function test_empty_string_returns_no_profanity()
    {
        $result = $this->blaspService->check('');

        $this->assertFalse($result->hasProfanity());
        $this->assertEquals(0, $result->getProfanitiesCount());
        $this->assertEmpty($result->getUniqueProfanitiesFound());
    }

    public function test_empty_string_returns_empty_source_and_clean_strings()
    {
        $result = $this->blaspService->check('');

        $this->assertEquals('', $result->getSourceString());
        $this->assertEquals('', $result->getCleanString());
    }

    public function test_null_returns_no_profanity()
    {
        $result = $this->blaspService->check(null);

        $this->assertFalse($result->hasProfanity());
        $this->assertEquals('', $result->getSourceString());
        $this->assertEquals('', $result->getCleanString());
    }

    public function test_profanity_still_detected_after_empty_check()
    {
        $this->blaspService->check('');
        $result = $this->blaspService->check('shit');

        $this->assertTrue($result->hasProfanity());
    }
}
