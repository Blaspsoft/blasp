<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Everyday sentences per language that must not be flagged. A word list that drifts into
 * ordinary vocabulary fails here first.
 */
class CleanSentencesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('blasp.cache.enabled', false);
    }

    public static function sentences(): array
    {
        return [
            ['french', 'Ma famille utilise internet tous les jours.'],
            ['french', 'Veuillez vérifier les informations sur cette page et envoyer un email.'],
            ['french', 'La collection de la bibliothèque est grande.'],
            ['french', 'La distribution des messages est rapide et le transport est efficace.'],
            ['french', 'Au sein de la famille, derrière la maison, ma tante aime les moules.'],
            ['french', 'Le poulet et le cochon sont dans la prairie près de l\'écurie.'],
            ['french', 'C\'est simple: les ordures vont dans la poubelle.'],
            ['german', 'Ich gehe zu meiner Mutter.'],
            ['german', 'Die Straße ist breit und die Stadt ist schön.'],
            ['german', 'Das Foto ist schwarz, matt und ziemlich dunkel.'],
            ['german', 'Bitte geben Sie Ihr Geschlecht an und folgen Sie dem Schild zum Ausgang.'],
            ['german', 'Zum Backen brauche ich Eier, Speck und einen Sack Mehl.'],
            ['german', 'Ich war krank und müde, der Drucker ist kaputt und die Datei ist weg.'],
            ['german', 'Das Glas ist voll, der Reifen ist platt und das Wetter ist schlecht.'],
            ['german', 'Der Staubsauger saugt gut, das Becken ist warm und der erste Schritt war falsch.'],
            ['german', 'Sie war arbeitslos, ihr Bruder ist schwul und beide besteigen gern einen Berg.'],
            ['spanish', 'El perro come en el jardín.'],
            ['spanish', 'Mi familia vive en Madrid.'],
            ['english', 'The hijacking was reported on the evening news.'],
            ['hindi', 'मेरे घर में एक कुत्ता और एक बिल्ली है।'],
            ['hindi', 'खेत में गधा और सुअर थे, और उल्लू रात में जागता है।'],
            ['hindi', 'बलात्कार के आरोपी को अदालत ने सजा सुनाई।'],
            ['hindi', 'लंडन में मसाला चाय बहुत महंगी है।'],
            ['hindi', 'mere ghar mein ek kutta hai aur khet mein gadha bhi.'],
            ['hindi', 'ullu raat mein jagta hai aur suar khet mein hai.'],
            ['nepali', 'हाम्रो घरमा एउटा कुकुर र एउटा बोका छ।'],
            ['nepali', 'गोरु र सुंगुर खेतमा छन्, जंगलमा भालु देखियो।'],
            ['nepali', 'hamro ghar ma kukur ra boka cha.'],
            ['nepali', 'goru ra sungur khet ma chan, jungle ma bhalu dekhiyo.'],
            ['all', 'Please check the information on this page and send an email.'],
            ['all', 'He played the lute in Ghent, which is Gand in French, and later moved to Lund.'],
            ['all', 'Il lutte contre la maladie.'],
            ['all', 'Paji, see you at the wedding!'],
        ];
    }

    #[DataProvider('sentences')]
    public function test_sentence_is_clean(string $language, string $sentence)
    {
        $check = $language === 'all' ? Blasp::inAllLanguages() : Blasp::in($language);
        $result = $check->check($sentence);

        $this->assertFalse($result->isOffensive(), 'Flagged: ' . implode(', ', $result->uniqueWords()));
    }

    public static function profanity(): array
    {
        return [
            ['french', 'putain de merde'], ['french', 'va te faire foutre, connard'], ['french', 'fils de pute'],
            ['french', 'espèce de salope'], ['french', 'quel con'], ['french', 'petite bite'],
            ['german', 'du Arschloch'], ['german', 'verfickte Scheiße'], ['german', 'du blöder Wichser'],
            ['german', 'so ein Hurensohn'], ['spanish', 'hijo de puta'],
            ['hindi', 'tu kutte ki aulad hai'], ['hindi', 'ullu ka pattha'], ['hindi', 'तू चूतिया है'],
            ['nepali', 'kukur ko choro'], ['nepali', 'त कुकुरको छोरो'],
        ];
    }

    #[DataProvider('profanity')]
    public function test_profanity_is_still_detected(string $language, string $text)
    {
        $this->assertTrue(Blasp::in($language)->check($text)->isOffensive());
    }
}
