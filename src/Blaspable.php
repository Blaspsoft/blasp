<?php

namespace Blaspsoft\Blasp;

use Closure;
use Blaspsoft\Blasp\Core\Result;
use Blaspsoft\Blasp\Events\ModelProfanityDetected;
use Blaspsoft\Blasp\Exceptions\ProfanityRejectedException;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @property array $blaspable
 * @property string $blaspMode
 * @property string|null $blaspLanguage
 * @property string|null $blaspMask
 */
trait Blaspable
{
    protected static bool $blaspCheckingDisabled = false;

    /** @var array<string, Result> */
    protected array $blaspResultsCache = [];

    public static function bootBlaspable(): void
    {
        static::saving(function (Model $model) {
            if (static::$blaspCheckingDisabled) {
                return;
            }

            $model->blaspResultsCache = [];

            $attributes = $model->blaspable ?? [];
            $dirty = $model->getDirty();
            $mode = $model->blaspMode ?? config('blasp.model.mode', 'sanitize');

            foreach ($attributes as $attr) {
                if (!isset($dirty[$attr]) || !is_string($dirty[$attr])) {
                    continue;
                }

                /** @var PendingCheck $check */
                $check = app('blasp')->newPendingCheck();

                if ($lang = ($model->blaspLanguage ?? null)) {
                    $check = $check->in($lang);
                }

                if ($mask = ($model->blaspMask ?? null)) {
                    $check = $check->mask($mask);
                }

                $result = $check->check($dirty[$attr]);
                $model->blaspResultsCache[$attr] = $result;

                if ($result->isOffensive()) {
                    event(new ModelProfanityDetected($model, $attr, $result));

                    if ($mode === 'reject') {
                        throw ProfanityRejectedException::forModel($model, $attr, $result);
                    }

                    $model->setAttribute($attr, $result->clean());
                }
            }
        });
    }

    public function hadProfanity(): bool
    {
        foreach ($this->blaspResultsCache as $result) {
            if ($result->isOffensive()) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, Result> */
    public function blaspResults(): array
    {
        return $this->blaspResultsCache;
    }

    public function blaspResult(string $attribute): ?Result
    {
        return $this->blaspResultsCache[$attribute] ?? null;
    }

    public static function withoutBlaspChecking(Closure $callback): mixed
    {
        $previousState = static::$blaspCheckingDisabled;
        static::$blaspCheckingDisabled = true;

        try {
            return $callback();
        } finally {
            static::$blaspCheckingDisabled = $previousState;
        }
    }
}
