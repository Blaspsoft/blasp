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
            ['spanish', 'El perro come en el jardín.'],
            ['spanish', 'Mi familia vive en Madrid.'],
            ['english', 'The hijacking was reported on the evening news.'],
            ['all', 'Please check the information on this page and send an email.'],
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
            ['german', 'du Arschloch'], ['spanish', 'hijo de puta'],
        ];
    }

    #[DataProvider('profanity')]
    public function test_profanity_is_still_detected(string $language, string $text)
    {
        $this->assertTrue(Blasp::in($language)->check($text)->isOffensive());
    }
}
