<?php

namespace Blaspsoft\Blasp\Testing;

use Blaspsoft\Blasp\Core\Result;
use Blaspsoft\Blasp\PendingCheck;
use PHPUnit\Framework\Assert;

class BlaspFake
{
    protected array $fakeResults;
    protected array $checksPerformed = [];

    public function __construct(array $fakeResults = [])
    {
        $this->fakeResults = $fakeResults;
    }

    public function check(?string $text): Result
    {
        $text = $text ?? '';
        $this->checksPerformed[] = $text;

        if (isset($this->fakeResults[$text])) {
            return $this->fakeResults[$text];
        }

        return Result::none($text);
    }

    public function checkMany(array $texts): array
    {
        $results = [];
        foreach ($texts as $key => $text) {
            $results[$key] = $this->check($text);
        }
        return $results;
    }

    public function assertChecked(): void
    {
        Assert::assertNotEmpty($this->checksPerformed, 'Expected at least one check to be performed.');
    }

    public function assertCheckedTimes(int $times): void
    {
        Assert::assertCount(
            $times,
            $this->checksPerformed,
            "Expected {$times} checks but " . count($this->checksPerformed) . ' were performed.'
        );
    }

    public function assertCheckedWith(string $text): void
    {
        Assert::assertContains($text, $this->checksPerformed, "Expected check with text: {$text}");
    }

    // Builder methods return self (no-op in fake mode, just pass through to check)
    public function __call(string $method, array $parameters): self
    {
        return $this;
    }

    public function in(string ...$languages): self
    {
        return $this;
    }

    public function inAllLanguages(): self
    {
        return $this;
    }

    public function allLanguages(): self
    {
        return $this;
    }

    public function english(): self
    {
        return $this;
    }

    public function spanish(): self
    {
        return $this;
    }

    public function german(): self
    {
        return $this;
    }

    public function french(): self
    {
        return $this;
    }

    public function dutch(): self
    {
        return $this;
    }

    public function mask(string $mask): self
    {
        return $this;
    }

    public function maskWith(string $character): self
    {
        return $this;
    }

    public function language(string $language): self
    {
        return $this;
    }

    public function driver(string $driver): self
    {
        return $this;
    }

    public function configure(?array $profanities = null, ?array $falsePositives = null): self
    {
        return $this;
    }
}
