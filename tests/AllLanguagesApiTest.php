<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;

class AllLanguagesApiTest extends TestCase
{
    public function test_all_languages_detection()
    {
        $result = Blasp::allLanguages()->check('This is fucking amazing');
        $this->assertTrue($result->hasProfanity());
        $this->assertEquals('This is ******* amazing', $result->getCleanString());

        $result = Blasp::allLanguages()->check('esto es una mierda');
        $this->assertTrue($result->hasProfanity());
        $this->assertEquals('esto es una ******', $result->getCleanString());

        $result = Blasp::allLanguages()->check('das ist scheiße');
        $this->assertTrue($result->hasProfanity());
        $this->assertEquals('das ist *******', $result->getCleanString());

        $result = Blasp::allLanguages()->check('c\'est de la merde');
        $this->assertTrue($result->hasProfanity());
        $this->assertEquals('c\'est de la *****', $result->getCleanString());
    }

    public function test_mixed_language_content()
    {
        $result = Blasp::allLanguages()->check('This shit is mierda, kut and scheiße');
        $this->assertTrue($result->hasProfanity());
        $this->assertEquals('This **** is ******, *** and *******', $result->getCleanString());
        $this->assertEquals(4, $result->getProfanitiesCount());
    }

    public function test_chainable_all_languages()
    {
        $result = Blasp::allLanguages()->check('damn merde');
        $this->assertTrue($result->hasProfanity());
    }

    public function test_language_shortcuts_vs_all()
    {
        $text = 'fucking merde scheiße mierda godverdomme';

        $englishResult = Blasp::english()->check($text);
        $this->assertEquals(1, $englishResult->getProfanitiesCount());

        $allResult = Blasp::allLanguages()->check($text);
        $this->assertEquals(5, $allResult->getProfanitiesCount());

        $this->assertStringNotContainsString('fucking', $allResult->getCleanString());
        $this->assertStringNotContainsString('merde', $allResult->getCleanString());
        $this->assertStringNotContainsString('scheiße', $allResult->getCleanString());
        $this->assertStringNotContainsString('godverdomme', $allResult->getCleanString());
        $this->assertStringContainsString('*******', $allResult->getCleanString());
    }

    public function test_direct_manager_all_languages()
    {
        $manager = app('blasp');
        $result = $manager->inAllLanguages()->check('This fuck is merde');
        $this->assertTrue($result->hasProfanity());
        $this->assertEquals(2, $result->getProfanitiesCount());
    }

    public function test_configure_with_all_languages()
    {
        $result = Blasp::allLanguages()
            ->block('customword')
            ->check('customword and fuck');

        $this->assertTrue($result->hasProfanity());
        $this->assertStringContainsString('*', $result->getCleanString());
    }
}
