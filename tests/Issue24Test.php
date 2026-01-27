<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\BlaspService;

class Issue24Test extends TestCase
{
    public function test_etre_not_flagged_as_profanity()
    {
        $service = new BlaspService();
        $result = $service->check('Le cadre pourrait être un peu mieux');
        $this->assertFalse($result->hasProfanity(), 'être should not be flagged. Found: ' . implode(', ', $result->getUniqueProfanitiesFound()));
    }

    public function test_are_accent_not_flagged()
    {
        $service = new BlaspService();
        $result = $service->check('aré');
        $this->assertFalse($result->hasProfanity(), 'aré should not be flagged. Found: ' . implode(', ', $result->getUniqueProfanitiesFound()));
    }

    public function test_tete_not_flagged()
    {
        $service = new BlaspService();
        $result = $service->check('tête tete');
        $this->assertFalse($result->hasProfanity(), 'tête should not be flagged. Found: ' . implode(', ', $result->getUniqueProfanitiesFound()));
    }

    public function test_actual_profanity_still_detected()
    {
        $service = new BlaspService();
        $result = $service->check('shit');
        $this->assertTrue($result->hasProfanity(), 'Actual profanity should still be detected after unicode fix');
    }
}
