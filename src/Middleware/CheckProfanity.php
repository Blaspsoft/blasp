<?php

namespace Blaspsoft\Blasp\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Blaspsoft\Blasp\BlaspManager;
use Blaspsoft\Blasp\Events\ContentBlocked;
use Blaspsoft\Blasp\Enums\Severity;

class CheckProfanity
{
    public function __construct(
        protected BlaspManager $manager
    ) {}

    public function handle(Request $request, Closure $next, ?string $action = null, ?string $severity = null): Response
    {
        $action = $action ?? config('blasp.middleware.action', 'reject');
        $minimumSeverity = $severity ? (Severity::tryFrom($severity) ?? Severity::Mild) : Severity::tryFrom(config('blasp.middleware.severity', 'mild'));
        $fields = config('blasp.middleware.fields', ['*']);
        $except = config('blasp.middleware.except', ['password', 'email', '_token']);

        if ($fields !== ['*']) {
            $input = collect($request->only($fields))->except($except)->all();
        } else {
            $input = $request->except($except);
        }

        $textFields = $this->extractTextFields($input);

        foreach ($textFields as $field => $value) {
            $pendingCheck = $this->manager->newPendingCheck();

            if ($minimumSeverity) {
                $pendingCheck = $pendingCheck->withSeverity($minimumSeverity);
            }

            $result = $pendingCheck->check($value);

            if ($result->isOffensive()) {
                if (config('blasp.events', false)) {
                    event(new ContentBlocked($result, $request, $field, $action));
                }

                if ($action === 'reject') {
                    return response()->json([
                        'message' => 'The request contains inappropriate content.',
                        'errors' => [$field => ['The ' . $field . ' field contains profanity.']],
                    ], 422);
                }

                if ($action === 'sanitize') {
                    $request->merge([$field => $result->clean()]);
                }
            }
        }

        return $next($request);
    }

    protected function extractTextFields(array $input): array
    {
        $fields = [];
        foreach ($input as $key => $value) {
            if (is_string($value) && !empty(trim($value))) {
                $fields[$key] = $value;
            }
        }
        return $fields;
    }
}
