<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;

class FalsePositiveFixesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('blasp.cache.enabled', false);
    }

    public static function cleanSentences(): array
    {
        return [
            'contraction then word' => ["Let's hit the road before the traffic gets bad."],
            "it's hit" => ["It's hit or miss with that restaurant."],
            "what's hit" => ["What's hit the hardest is the budget."],
            "it's hitting" => ["It's hitting the shelves next week."],
            "he'll" => ["He'll be there at five."],
            "she'll" => ['She’ll go to the meeting tomorrow.'],
            'a hole' => ['There is a hole in the road.'],
            'dam nation' => ['The dam nation project was approved.'],
            'money shot' => ['That photo was the money shot of the campaign.'],
            'star rating' => ['I rate it ***** out of five.'],
            'markdown' => ['This is **bold** and this is *italic* text.'],
            'masked password' => ['Your password: ******** has been reset.'],
            'spicy' => ['The spicy food was great.'],
            'cocker spaniel' => ['We adopted a cocker spaniel.'],
            'prickly pear' => ['The prickly pear grows in the desert.'],
            'door knobs' => ['The door knobs need replacing.'],
            'stretched greeting' => ['Hellllo there, how are you?'],
            'stretched word' => ['Sooo classsy, I love it.'],
        ];
    }

    #[DataProvider('cleanSentences')]
    public function test_clean_sentence_is_not_flagged(string $sentence)
    {
        $result = Blasp::check($sentence);

        $this->assertFalse($result->isOffensive(), 'Flagged: ' . implode(', ', $result->uniqueWords()));
    }

    public static function obfuscations(): array
    {
        return [
            ['f*u*c*k'], ['f*ck'], ['f**k'], ['sh*t'], ['a$$hole'], ['@ss'], ['b!tch'], ['*sshole'],
            ['f u c k'], ['sh it'], ['a-s-s'], ['what the f***'], ['fuuuuuck'], ['FFuukkaahh'],
        ];
    }

    #[DataProvider('obfuscations')]
    public function test_obfuscations_are_still_detected(string $text)
    {
        $this->assertTrue(Blasp::check($text)->isOffensive());
    }

    public function test_phrases_containing_a_profanity_still_match_whole()
    {
        $this->assertSame('********', Blasp::check('fuck you')->clean());
        $this->assertSame('*********', Blasp::check('bull shit')->clean());
    }

    public function test_contraction_of_a_profanity_is_still_masked()
    {
        $this->assertTrue(Blasp::check("that's bullshit's fault")->isOffensive());
        $this->assertSame('********', Blasp::check("nigger's")->clean());
    }

    public function test_stretched_match_inside_a_word_reports_the_right_word()
    {
        // "crapped" used to be reported as "raped" (embedded, stretched p)
        $this->assertSame(['crap'], Blasp::check('The cows crapped in the field.')->uniqueWords());
    }

    public function test_capitalised_list_words_match_all_spellings()
    {
        $this->assertTrue(Blasp::check('fffukah')->isOffensive());
    }
}
