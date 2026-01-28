<?php

namespace Blaspsoft\Blasp\Tests;

use Exception;
use Blaspsoft\Blasp\BlaspService;

class BlaspCheckTest extends TestCase
{
    protected $blaspService;

    public function setUp(): void
    {
        parent::setUp();
        $this->blaspService = new BlaspService();
    }

    /**
     * @throws Exception
     */
    public function test_real_blasp_service()
    {
        $result =  $this->blaspService->check('This is a fuck!ng sentence');
        
        $this->assertTrue($result->hasProfanity);
    }

    /**
     * @throws Exception
     */
    public function test_straight_match()
    {
        $result =  $this->blaspService->check('This is a fucking sentence');
    
        $this->assertTrue($result->hasProfanity);
        $this->assertSame(1, $result->profanitiesCount);
        $this->assertCount(1, $result->uniqueProfanitiesFound);
        $this->assertSame('This is a ******* sentence', $result->cleanString);
    }

    /**
     * @throws Exception
     */
    public function test_substitution_match()
    {
        $result =  $this->blaspService->check('This is a fÛck!ng sentence');

        $this->assertTrue($result->hasProfanity);
        $this->assertSame(1, $result->profanitiesCount);
        $this->assertCount(1, $result->uniqueProfanitiesFound);
        $this->assertSame('This is a ******* sentence', $result->cleanString);
    }

    /**
     * @throws Exception
     */
    public function test_obscured_match()
    {
        $result =  $this->blaspService->check('This is a f-u-c-k-i-n-g sentence');

        $this->assertTrue($result->hasProfanity);
        $this->assertSame(1, $result->profanitiesCount);
        $this->assertCount(1, $result->uniqueProfanitiesFound);
        $this->assertSame('This is a ************* sentence', $result->cleanString);
    }

    /**
     * @throws Exception
     */
    public function test_doubled_match()
    {
        $result =  $this->blaspService->check('This is a ffuucckkiinngg sentence');

        $this->assertTrue($result->hasProfanity);
        $this->assertSame(1, $result->profanitiesCount);
        $this->assertCount(1, $result->uniqueProfanitiesFound);
        $this->assertSame('This is a ************** sentence', $result->cleanString);
    }

    /**
     * @throws Exception
     */
    public function test_combination_match()
    {
        $result =  $this->blaspService->check('This is a f-uuck!ng sentence');

        $this->assertTrue($result->hasProfanity);
        $this->assertSame(1, $result->profanitiesCount);
        $this->assertCount(1, $result->uniqueProfanitiesFound);
        $this->assertSame('This is a ********* sentence', $result->cleanString);
    }

    /**
     * @throws Exception
     */
    public function test_multiple_profanities_no_spaces()
    {
        $result =  $this->blaspService->check('cuntfuck shit');

        $this->assertTrue($result->hasProfanity);
        $this->assertSame(3, $result->profanitiesCount);
        $this->assertCount(3, $result->uniqueProfanitiesFound);
        $this->assertSame('******** ****', $result->cleanString);
    }

    /**
     * @throws Exception
     */
    public function test_multiple_profanities()
    {
        $result =  $this->blaspService->check('This is a fuuckking sentence you fucking cunt!');
        $this->assertTrue($result->hasProfanity);
        $this->assertSame(3, $result->profanitiesCount);
        $this->assertCount(2, $result->uniqueProfanitiesFound);
        $this->assertSame('This is a ********* sentence you ******* ****!', $result->cleanString);
    }

    /**
     * @throws Exception
     */
    public function test_scunthorpe_problem()
    {
        $result =  $this->blaspService->check('I live in a town called Scunthorpe');

        $this->assertTrue(!$result->hasProfanity);
        $this->assertSame(0, $result->profanitiesCount);
        $this->assertCount(0, $result->uniqueProfanitiesFound);
        $this->assertSame('I live in a town called Scunthorpe', $result->cleanString);
    }

    /**
     * @throws Exception
     */
    public function test_penistone_problem()
    {
        $result =  $this->blaspService->check('I live in a town called Penistone');

        $this->assertTrue(!$result->hasProfanity);
        $this->assertSame(0, $result->profanitiesCount);
        $this->assertCount(0, $result->uniqueProfanitiesFound);
        $this->assertSame('I live in a town called Penistone', $result->cleanString);
    }

    /**
     * @throws Exception
     */
    public function test_false_positives()
    {
        $words = [
            'Blackcocktail',
            'Scunthorpe',
            'Cockburn',
            'Penistone',
            'Lightwater',
            'Assume',
            'Bass',
            'Class',
            'Compass',
            'Pass',
            'Dickinson',
            'Middlesex',
            'Cockerel',
            'Butterscotch',
            'Blackcock',
            'Countryside',
            'Arsenal',
            'Flick',
            'Flicker',
            'Analyst',
        ];

        foreach ($words as $word) {

            $result =  $this->blaspService->check($word);

            try {
                $this->assertTrue(!$result->hasProfanity);
                $this->assertSame(0, $result->profanitiesCount);
                $this->assertCount(0, $result->uniqueProfanitiesFound);
                $this->assertSame($word, $result->cleanString);       
            } catch (\Exception $e) {
                dd($result);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function test_cuntfuck_fuckcunt()
    {
        $result =  $this->blaspService->check('cuntfuck fuckcunt');
        $this->assertTrue($result->hasProfanity);
        $this->assertSame(4, $result->profanitiesCount);
        $this->assertCount(2, $result->uniqueProfanitiesFound);
        $this->assertSame('******** ********', $result->cleanString);
    }

    /**
     * @throws Exception
     */
    public function test_fucking_shit_cunt_fuck()
    {
        $result =  $this->blaspService->check('fuckingshitcuntfuck');
        $this->assertTrue($result->hasProfanity);
        $this->assertSame(3, $result->profanitiesCount);
        $this->assertCount(3, $result->uniqueProfanitiesFound);
        $this->assertSame('*******************', $result->cleanString);
    }

    /**
     * @throws Exception
     */
    public function test_billy_butcher()
    {
        $result =  $this->blaspService->check('oi! cunt!');
        $this->assertTrue($result->hasProfanity);
        $this->assertSame(1, $result->profanitiesCount);
        $this->assertCount(1, $result->uniqueProfanitiesFound);
        $this->assertSame('oi! ****!', $result->cleanString);
    }

    /**
     * @throws Exception
     */
    public function test_paragraph()
    {
        $paragraph = "This damn project is such a pain in the ass. I can't believe I have to deal with this bullshit every single day. It's like everything is completely fucked up, and nobody gives a shit. Sometimes I just want to scream, 'What the hell is going on?' Honestly, it's a total clusterfuck, and I'm so fucking done with this crap.";
        
        $result =  $this->blaspService->check($paragraph);
    
        $expectedOutcome = "This **** project is such a pain in the ***. I can't believe I have to deal with this ******** every single day. It's like everything is completely ****** up, and nobody gives a ****. Sometimes I just want to scream, 'What the **** is going on?' Honestly, it's a total ***********, and I'm so ******* done with this ****.";

        $this->assertTrue($result->hasProfanity);
        $this->assertSame(9, $result->profanitiesCount);
        $this->assertCount(9, $result->uniqueProfanitiesFound);
        $this->assertSame($expectedOutcome, $result->cleanString);
    }

    public function test_word_boudary()
    {
        $result =  $this->blaspService->check('afuckb');
        $this->assertTrue($result->hasProfanity);
        $this->assertSame(1, $result->profanitiesCount);
        $this->assertCount(1, $result->uniqueProfanitiesFound);
        $this->assertSame('a****b', $result->cleanString);
    }

    public function test_pural_profanity()
    {
        $result =  $this->blaspService->check('fuckings');
        $this->assertTrue($result->hasProfanity);
        $this->assertSame(1, $result->profanitiesCount);
        $this->assertCount(1, $result->uniqueProfanitiesFound);
        $this->assertSame('*******s', $result->cleanString);
    }

    public function test_this_musicals_hit()
    {
        $result =  $this->blaspService->check('This musicals hit');
        $this->assertTrue(!$result->hasProfanity);
        $this->assertSame(0, $result->profanitiesCount);
        $this->assertCount(0, $result->uniqueProfanitiesFound);
        $this->assertSame('This musicals hit', $result->cleanString);
    }

    public function test_ass_subtitution()
    {
        $result =  $this->blaspService->check('a$$');
        $this->assertTrue($result->hasProfanity);
        $this->assertSame(1, $result->profanitiesCount);
        $this->assertCount(1, $result->uniqueProfanitiesFound);
        $this->assertSame('***', $result->cleanString);
    }

    public function test_embedded_profanities()
    {
        $result =  $this->blaspService->check('abcdtwatefghshitijklmfuckeropqrccuunntt');
        $this->assertTrue($result->hasProfanity);
        $this->assertSame(4, $result->profanitiesCount);
        $this->assertCount(4, $result->uniqueProfanitiesFound);
        $this->assertSame('abcd****efgh****ijklm******opqr********', $result->cleanString);
    }

    public function test_multiple_profanities_with_spaces()
    {
        $result =  $this->blaspService->check('This is a fucking shit sentence');
        $this->assertTrue($result->hasProfanity);
        $this->assertSame(2, $result->profanitiesCount);
        $this->assertCount(2, $result->uniqueProfanitiesFound);
        $this->assertSame('This is a ******* **** sentence', $result->cleanString);
    }

    public function test_spaced_profanity_with_substitution()
    {
        // Issue #36 - README example should be detected
        $result = $this->blaspService->check('This is f u c k 1 n g awesome!');

        $this->assertTrue($result->hasProfanity);
        $this->assertStringContainsString('*', $result->cleanString);
    }

    public function test_spaced_profanity_without_substitution()
    {
        $result = $this->blaspService->check('f u c k i n g');

        $this->assertTrue($result->hasProfanity);
    }

    public function test_partial_spacing_s_hit()
    {
        $result = $this->blaspService->check('s hit');
        $this->assertTrue($result->hasProfanity);
        $this->assertContains('shit', $result->uniqueProfanitiesFound);
    }

    public function test_partial_spacing_f_uck()
    {
        $result = $this->blaspService->check('f uck');
        $this->assertTrue($result->hasProfanity);
        $this->assertContains('fuck', $result->uniqueProfanitiesFound);
    }

    public function test_partial_spacing_t_wat()
    {
        $result = $this->blaspService->check('t wat');
        $this->assertTrue($result->hasProfanity);
        $this->assertContains('twat', $result->uniqueProfanitiesFound);
    }

    public function test_partial_spacing_fu_c_k()
    {
        $result = $this->blaspService->check('fu c k');
        $this->assertTrue($result->hasProfanity);
        $this->assertContains('fuck', $result->uniqueProfanitiesFound);
    }

    public function test_partial_spacing_tw_a_t()
    {
        $result = $this->blaspService->check('tw a t');
        $this->assertTrue($result->hasProfanity);
        $this->assertContains('twat', $result->uniqueProfanitiesFound);
    }

    public function test_no_false_positive_musicals_hit_embedded()
    {
        $result = $this->blaspService->check('This musicals hit');
        $this->assertFalse($result->hasProfanity);
        $this->assertSame('This musicals hit', $result->cleanString);
    }
}