<?php

namespace Blaspsoft\Blasp\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Blaspsoft\Blasp\BlaspManager;
use Blaspsoft\Blasp\Testing\BlaspFake;
use Blaspsoft\Blasp\Events\ContentBlocked;
use Blaspsoft\Blasp\Enums\Severity;

class CheckProfanity
{
    private const ACTIONS = ['reject', 'sanitize'];

    /**
     * The manager is resolved from the container when the request is handled (unless one
     * is passed in), so routes using the middleware also work after Blasp::fake().
     */
    public function __construct(
        protected BlaspManager|BlaspFake|null $manager = null
    ) {}

    public function handle(Request $request, Closure $next, ?string $action = null, ?string $severity = null): Response
    {
        $action = $action ?? config('blasp.middleware.action', 'reject');
        if (!in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException(sprintf('Invalid blasp middleware action "%s". Use one of: %s.', $action, implode(', ', self::ACTIONS)));
        }

        $minimumSeverity = $this->resolveSeverity($severity ?? config('blasp.middleware.severity', 'mild'));
        $fields = config('blasp.middleware.fields', ['*']);
        $except = config('blasp.middleware.except', ['password', 'email', '_token']);

        $maxLength = BlaspManager::maxLength();
        $errors = [];

        foreach ($this->extractTextFields($request->all(), $fields, $except) as $field => $value) {
            // Too long to check: rejected in either mode, since unchecked text cannot be
            // passed on as sanitized
            if ($maxLength !== null && mb_strlen($value, 'UTF-8') > $maxLength) {
                $errors[$field] = ['The ' . $field . ' field is too long to check for profanity.'];
                continue;
            }

            $pendingCheck = ($this->manager ?? app('blasp'))->newPendingCheck();

            if ($minimumSeverity) {
                $pendingCheck = $pendingCheck->withSeverity($minimumSeverity);
            }

            $result = $pendingCheck->check($value);

            if (!$result->isOffensive()) {
                continue;
            }

            if (config('blasp.events', false)) {
                event(new ContentBlocked($result, $request, $field, $action));
            }

            if ($action === 'reject') {
                $errors[$field] = ['The ' . $field . ' field contains profanity.'];
            } else {
                $this->replaceInput($request, $field, $result->clean());
            }
        }

        if ($errors !== []) {
            // JSON clients (by Accept header or JSON body) get the same response as before;
            // form posts are redirected back with the errors, like any other failed validation
            if ($request->expectsJson() || $request->isJson()) {
                return response()->json([
                    'message' => 'The request contains inappropriate content.',
                    'errors' => $errors,
                ], 422);
            }

            throw ValidationException::withMessages($errors);
        }

        return $next($request);
    }

    /**
     * Every non-empty string in the input, keyed by its dotted path ("comment.body",
     * "tags.0"), so nested and array input is checked too.
     *
     * @return array<string, string>
     */
    protected function extractTextFields(array $input, array $fields = ['*'], array $except = []): array
    {
        $texts = [];

        foreach (Arr::dot($input) as $key => $value) {
            $key = (string) $key;

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            if ($fields !== ['*'] && !$this->matches($key, $fields)) {
                continue;
            }

            if ($this->matches($key, $except) || $this->isPasswordField($key)) {
                continue;
            }

            $texts[$key] = $value;
        }

        return $texts;
    }

    /**
     * A dotted key matches a pattern by its full path ("user.bio", "comments.*.body") or,
     * for patterns without a dot, by its last segment ("email" also matches "user.email").
     */
    private function matches(string $key, array $patterns): bool
    {
        $last = Str::afterLast($key, '.');

        foreach ($patterns as $pattern) {
            $pattern = (string) $pattern;

            if (Str::is($pattern, $key) || $key === $pattern || Str::startsWith($key, $pattern . '.')) {
                return true;
            }

            if (!str_contains($pattern, '.') && Str::is($pattern, $last)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Passwords are never checked: rejecting "Shit12345" is unhelpful and sanitizing one
     * would silently change it.
     */
    private function isPasswordField(string $key): bool
    {
        // Any segment, so values under a password field ("password.new") are skipped too
        return str_contains(strtolower($key), 'password');
    }

    private function replaceInput(Request $request, string $field, string $clean): void
    {
        $top = Str::before($field, '.');

        if ($top === $field) {
            $request->merge([$field => $clean]);
            return;
        }

        $value = $request->input($top);
        data_set($value, Str::after($field, '.'), $clean);
        $request->merge([$top => $value]);
    }

    private function resolveSeverity(mixed $severity): ?Severity
    {
        if ($severity === null || $severity instanceof Severity) {
            return $severity;
        }

        return Severity::tryFrom(strtolower(trim((string) $severity)))
            ?? throw new InvalidArgumentException(sprintf(
                'Invalid blasp middleware severity "%s". Use one of: %s.',
                $severity,
                implode(', ', array_map(fn (Severity $s) => $s->value, Severity::cases()))
            ));
    }
}
