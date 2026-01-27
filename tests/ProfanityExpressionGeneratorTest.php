<?php

namespace Blaspsoft\Blasp\Tests;

use Blaspsoft\Blasp\Generators\ProfanityExpressionGenerator;

class ProfanityExpressionGeneratorTest extends TestCase
{
    private ProfanityExpressionGenerator $generator;

    public function setUp(): void
    {
        parent::setUp();
        $this->generator = new ProfanityExpressionGenerator();
    }

    public function test_generate_separator_expression()
    {
        $separators = ['-', '_', '.', ' '];
        $result = $this->generator->generateSeparatorExpression($separators);
        
        // Should create a pattern that matches separators
        $this->assertIsString($result);
        $this->assertStringContainsString('[', $result);
        $this->assertStringContainsString(']', $result);
    }

    public function test_generate_substitution_expressions()
    {
        $substitutions = [
            '/a/' => ['a', '@', '4'],
            '/e/' => ['e', '3'],
            '/o/' => ['o', '0']
        ];
        
        $result = $this->generator->generateSubstitutionExpressions($substitutions);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('/a/', $result);
        $this->assertArrayHasKey('/e/', $result);
        $this->assertArrayHasKey('/o/', $result);
        
        // Each substitution should contain the character options
        foreach ($result as $expression) {
            $this->assertStringContainsString('[', $expression);
            $this->assertStringContainsString(']', $expression);
        }
    }

    public function test_generate_profanity_expression_simple()
    {
        $profanity = 'test';
        $substitutionExpressions = [
            '/t/' => '[t\+]+{!!}',
            '/e/' => '[e3]+{!!}',
            '/s/' => '[s$]+{!!}'
        ];
        $separatorExpression = '[\-\s]*?';
        
        $result = $this->generator->generateProfanityExpression(
            $profanity,
            $substitutionExpressions,
            $separatorExpression
        );
        
        $this->assertIsString($result);
        $this->assertStringStartsWith('/', $result);
        $this->assertStringEndsWith('/iu', $result);
    }

    public function test_generate_expressions_full_flow()
    {
        $profanities = ['fuck', 'shit'];
        $separators = ['-', '_', '.'];
        $substitutions = [
            '/f/' => ['f', 'ƒ'],
            '/u/' => ['u', 'υ', 'µ'],
            '/c/' => ['c', 'ç', '¢'],
            '/s/' => ['s', '5', '$'],
            '/h/' => ['h'],
            '/i/' => ['i', '!', '|'],
            '/t/' => ['t']
        ];
        
        $result = $this->generator->generateExpressions($profanities, $separators, $substitutions);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('fuck', $result);
        $this->assertArrayHasKey('shit', $result);
        
        // Each expression should be a valid regex
        foreach ($result as $profanity => $expression) {
            $this->assertIsString($expression);
            $this->assertStringStartsWith('/', $expression);
            $this->assertStringEndsWith('/iu', $expression);
            
            // Verify it's a valid regex by testing it doesn't throw error
            $testResult = @preg_match($expression, $profanity);
            $this->assertNotFalse($testResult, "Invalid regex generated for '$profanity': $expression");
        }
    }

    public function test_generated_expressions_match_profanities()
    {
        $profanities = ['fuck'];
        $separators = ['-', '_'];
        $substitutions = [
            '/f/' => ['f', 'ƒ'],
            '/u/' => ['u', 'υ', 'µ'],
            '/c/' => ['c', 'ç', '¢'],
            '/k/' => ['k']
        ];
        
        $expressions = $this->generator->generateExpressions($profanities, $separators, $substitutions);
        $expression = $expressions['fuck'];
        
        // Test basic matches
        $this->assertEquals(1, preg_match($expression, 'fuck'));
        $this->assertEquals(1, preg_match($expression, 'FUCK'));
        $this->assertEquals(1, preg_match($expression, 'ƒuck'));
        $this->assertEquals(1, preg_match($expression, 'fuçk'));
        
        // Test with separators
        $this->assertEquals(1, preg_match($expression, 'f-u-c-k'));
        $this->assertEquals(1, preg_match($expression, 'f_u_c_k'));
        
        // Test non-matches
        $this->assertEquals(0, preg_match($expression, 'hello'));
        $this->assertEquals(0, preg_match($expression, 'world'));
    }

    public function test_separator_expression_with_various_chars()
    {
        $separators = ['-', '_', '.', ' ', '*', '!'];
        $result = $this->generator->generateSeparatorExpression($separators);
        
        $this->assertIsString($result);
        
        // Test that the generated separator expression works
        $testExpression = '/f' . $result . 'u' . $result . 'c' . $result . 'k/i';
        
        // Should match separated versions
        $this->assertEquals(1, preg_match($testExpression, 'f-u-c-k'));
        $this->assertEquals(1, preg_match($testExpression, 'f_u_c_k'));
        $this->assertEquals(1, preg_match($testExpression, 'f u c k'));
        $this->assertEquals(1, preg_match($testExpression, 'f*u*c*k'));
        $this->assertEquals(1, preg_match($testExpression, 'f!u!c!k'));
        
        // Should also match without separators
        $this->assertEquals(1, preg_match($testExpression, 'fuck'));
    }

    public function test_substitution_expressions_with_special_chars()
    {
        $substitutions = [
            '/a/' => ['a', '@', '4', 'α'],
            '/e/' => ['e', '3', '€', 'ε'],
            '/o/' => ['o', '0', 'ø', 'Ω']
        ];
        
        $result = $this->generator->generateSubstitutionExpressions($substitutions);
        
        foreach ($result as $key => $expression) {
            $this->assertIsString($expression);
            $this->assertStringContainsString('[', $expression);
            $this->assertStringContainsString(']', $expression);
            $this->assertStringContainsString('+', $expression);
        }
    }

    public function test_generate_expressions_with_multi_char_substitutions()
    {
        $profanities = ['ass'];
        $separators = ['-'];
        $substitutions = [
            '/a/' => ['a', '@', '4'],
            '/s/' => ['s', '$', '5']
        ];
        
        $expressions = $this->generator->generateExpressions($profanities, $separators, $substitutions);
        $expression = $expressions['ass'];
        
        // Test various substitutions
        $this->assertEquals(1, preg_match($expression, 'ass'));
        $this->assertEquals(1, preg_match($expression, '@ss'));
        $this->assertEquals(1, preg_match($expression, '4ss'));
        $this->assertEquals(1, preg_match($expression, 'a$s'));
        $this->assertEquals(1, preg_match($expression, 'a55'));
        $this->assertEquals(1, preg_match($expression, '@$$'));
        $this->assertEquals(1, preg_match($expression, '455'));
    }

    public function test_expressions_are_case_insensitive()
    {
        $profanities = ['test'];
        $separators = [];
        $substitutions = [
            '/t/' => ['t'],
            '/e/' => ['e', '3'],
            '/s/' => ['s', '$']
        ];
        
        $expressions = $this->generator->generateExpressions($profanities, $separators, $substitutions);
        $expression = $expressions['test'];
        
        // Test case insensitivity
        $this->assertEquals(1, preg_match($expression, 'test'));
        $this->assertEquals(1, preg_match($expression, 'TEST'));
        $this->assertEquals(1, preg_match($expression, 'Test'));
        $this->assertEquals(1, preg_match($expression, 'TeSt'));
        $this->assertEquals(1, preg_match($expression, 't3st'));
        $this->assertEquals(1, preg_match($expression, 'T3ST'));
        $this->assertEquals(1, preg_match($expression, 'te$t'));
    }

    public function test_empty_arrays_handling()
    {
        $result = $this->generator->generateExpressions([], [], []);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
        
        $separatorResult = $this->generator->generateSeparatorExpression([]);
        $this->assertIsString($separatorResult);
        
        $substitutionResult = $this->generator->generateSubstitutionExpressions([]);
        $this->assertIsArray($substitutionResult);
        $this->assertEmpty($substitutionResult);
    }

    public function test_complex_profanity_patterns()
    {
        $profanities = ['fucking', 'bullshit'];
        $separators = ['-', '_', ' ', '.'];
        $substitutions = [
            '/f/' => ['f'],
            '/u/' => ['u', 'ü', 'ū'],
            '/c/' => ['c', 'ç'],
            '/k/' => ['k'],
            '/i/' => ['i', '!', '1'],
            '/n/' => ['n', 'ñ'],
            '/g/' => ['g'],
            '/b/' => ['b', 'ß'],
            '/l/' => ['l'],
            '/s/' => ['s', '$'],
            '/h/' => ['h'],
            '/t/' => ['t']
        ];
        
        $expressions = $this->generator->generateExpressions($profanities, $separators, $substitutions);
        
        // Test complex variations
        $fuckingExpression = $expressions['fucking'];
        $this->assertEquals(1, preg_match($fuckingExpression, 'fucking'));
        $this->assertEquals(1, preg_match($fuckingExpression, 'füçk1ng'));
        $this->assertEquals(1, preg_match($fuckingExpression, 'f-u-c-k-i-n-g'));
        
        $bullshitExpression = $expressions['bullshit'];
        $this->assertEquals(1, preg_match($bullshitExpression, 'bullshit'));
        $this->assertEquals(1, preg_match($bullshitExpression, 'ßull$h1t'));
        $this->assertEquals(1, preg_match($bullshitExpression, 'b.u.l.l.s.h.i.t'));
    }

    public function test_period_handling_in_separators()
    {
        $separators = ['-', '.', ' '];
        $separatorExpression = $this->generator->generateSeparatorExpression($separators);
        
        // Create a test pattern with the separator
        $testPattern = '/t' . $separatorExpression . 'e' . $separatorExpression . 's' . $separatorExpression . 't/i';
        
        // Should match with various separators
        $this->assertEquals(1, preg_match($testPattern, 'test'));
        $this->assertEquals(1, preg_match($testPattern, 't-e-s-t'));
        $this->assertEquals(1, preg_match($testPattern, 't e s t'));
        $this->assertEquals(1, preg_match($testPattern, 't.e.s.t'));
        $this->assertEquals(1, preg_match($testPattern, 't-.e.s-t'));
    }

    public function test_circular_substitutions_produce_valid_regex()
    {
        $substitutions = [
            '/c/' => ['c', 'k', 'ç'],
            '/k/' => ['k', 'c', 'q'],
        ];
        $subExpressions = $this->generator->generateSubstitutionExpressions($substitutions);
        $separatorExpr = $this->generator->generateSeparatorExpression([]);
        $regex = $this->generator->generateProfanityExpression('cock', $subExpressions, $separatorExpr);

        // Regex should be valid (no nested brackets)
        $this->assertNotFalse(@preg_match($regex, ''));

        // Should match the original word
        $this->assertMatchesRegularExpression($regex, 'cock');

        // Should match with substitutions
        $this->assertMatchesRegularExpression($regex, 'kokk');
        $this->assertMatchesRegularExpression($regex, 'çoçk');
    }

    public function test_multi_char_substitutions()
    {
        $substitutions = [
            '/p/' => ['p'],
            '/h/' => ['h'],
            '/ph/' => ['ph', 'f'],
        ];
        $subExpressions = $this->generator->generateSubstitutionExpressions($substitutions);
        $separatorExpr = $this->generator->generateSeparatorExpression([]);
        $regex = $this->generator->generateProfanityExpression('phone', $subExpressions, $separatorExpr);

        // 'ph' should be consumed as one unit, matching 'f'
        $this->assertMatchesRegularExpression($regex, 'phone');
        $this->assertMatchesRegularExpression($regex, 'fone');
    }

    public function test_basic_profanity_matching()
    {
        $profanities = ['damn', 'hell'];
        $separators = ['-', '_'];
        $substitutions = [
            '/a/' => ['a', '@'],
            '/e/' => ['e', '3'],
            '/l/' => ['l', '1']
        ];
        
        $expressions = $this->generator->generateExpressions($profanities, $separators, $substitutions);
        $damnExpression = $expressions['damn'];
        $hellExpression = $expressions['hell'];
        
        // Test basic matches
        $this->assertEquals(1, preg_match($damnExpression, 'damn'));
        $this->assertEquals(1, preg_match($damnExpression, 'd@mn'));
        $this->assertEquals(1, preg_match($hellExpression, 'hell'));
        $this->assertEquals(1, preg_match($hellExpression, 'h3ll'));
        $this->assertEquals(1, preg_match($hellExpression, 'he11'));
        
        // Test non-matches
        $this->assertEquals(0, preg_match($damnExpression, 'hello'));
        $this->assertEquals(0, preg_match($hellExpression, 'damn'));
    }
}