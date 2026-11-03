<?php

namespace Blaspsoft\Blasp\Rules;

use Closure;
use Blaspsoft\Blasp\BlaspManager;
use Illuminate\Contracts\Validation\ValidationRule;
use Blaspsoft\Blasp\Enums\Severity;

/**
 * Each option works both statically and on an instance:
 * Profanity::in('spanish') and Profanity::make()->in('spanish').
 *
 * @method static self in(string $language)
 * @method static self maxScore(int $score)
 * @method static self severity(Severity $severity)
 * @method static self maxLength(int $characters)
 * @method self in(string $language)
 * @method self maxScore(int $score)
 * @method self severity(Severity $severity)
 * @method self maxLength(int $characters)
 */
class Profanity implements ValidationRule
{
    protected ?string $language = null;
    protected ?int $maxScore = null;
    protected ?Severity $minimumSeverity = null;
    protected ?int $maxLength = null;

    public static function make(): self
    {
        return new self();
    }

    // The options are routed through __call/__callStatic because PHP cannot declare a
    // static and an instance method with the same name; as public instance methods,
    // the documented Profanity::in(...) threw "cannot be called statically".
    protected function withLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }

    protected function withMaxScore(int $score): self
    {
        $this->maxScore = $score;
        return $this;
    }

    protected function withSeverity(Severity $severity): self
    {
        $this->minimumSeverity = $severity;
        return $this;
    }

    protected function withMaxLength(int $characters): self
    {
        $this->maxLength = $characters;
        return $this;
    }

    public function __call(string $name, array $arguments): self
    {
        return match ($name) {
            'in' => $this->withLanguage(...$arguments),
            'maxLength' => $this->withMaxLength(...$arguments),
            'maxScore' => $this->withMaxScore(...$arguments),
            'severity' => $this->withSeverity(...$arguments),
            default => throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $name)),
        };
    }

    public static function __callStatic(string $name, array $arguments): self
    {
        return (new self())->__call($name, $arguments);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return;
        }

        $maxLength = $this->maxLength ?? BlaspManager::maxLength();
        if ($maxLength !== null && mb_strlen($value, 'UTF-8') > $maxLength) {
            $fail('The :attribute is too long to check for profanity.');
            return;
        }

        $manager = app('blasp');
        $pendingCheck = $manager->newPendingCheck();

        if ($this->language) {
            $pendingCheck = $pendingCheck->in($this->language);
        }

        if ($this->minimumSeverity) {
            $pendingCheck = $pendingCheck->withSeverity($this->minimumSeverity);
        }

        $result = $pendingCheck->check($value);

        if ($this->maxScore !== null) {
            if ($result->score() > $this->maxScore) {
                $fail('The :attribute contains profanity.');
            }
            return;
        }

        if ($result->isOffensive()) {
            $fail('The :attribute contains profanity.');
        }
    }
}
