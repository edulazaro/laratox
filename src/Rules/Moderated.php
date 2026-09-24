<?php

namespace EduLazaro\Laratox\Rules;

use Closure;
use EduLazaro\Laratox\Concerns\ConfiguresCheck;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use ToxicFilter\Exception\ApiError;
use ToxicFilter\Verdict;

/**
 * Fails a field that ToxicFilter blocks.
 *
 *   'body' => ['bail', 'required', 'string', Moderated::text()->policy('comments')],
 *
 * A `review` passes unless `orReview()` is called. Put it last, after `bail`: it is a
 * network call.
 */
class Moderated implements ValidationRule
{
    use ConfiguresCheck;

    /** The kinds one field can be checked as. */
    private const KINDS = ['text', 'name', 'email', 'url', 'prompt', 'image'];

    private bool $refuseReview = false;

    /** @var array<string, Verdict> */
    private array $verdicts = [];

    /**
     * @param string $kind
     *
     * @throws InvalidArgumentException On an unknown kind.
     */
    public function __construct(private readonly string $kind = 'text')
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("laratox: [{$kind}] is not one of: " . implode(', ', self::KINDS) . '.');
        }

        $this->useDefaultProject();
    }

    /**
     * @return static
     */
    public static function text(): static
    {
        return new static('text');
    }

    /**
     * @return static
     */
    public static function name(): static
    {
        return new static('name');
    }

    /**
     * @return static
     */
    public static function email(): static
    {
        return new static('email');
    }

    /**
     * @return static
     */
    public static function url(): static
    {
        return new static('url');
    }

    /**
     * @return static
     */
    public static function prompt(): static
    {
        return new static('prompt');
    }

    /**
     * @return static
     */
    public static function image(): static
    {
        return new static('image');
    }

    /**
     * Fail on `review` too.
     *
     * @return static
     */
    public function orReview(): static
    {
        $this->refuseReview = true;

        return $this;
    }

    /**
     * The verdict for a field, or for the last field checked. Null if it was not checked.
     *
     * @param string|null $attribute E.g. `body`, or `tags.2` for a wildcard rule.
     * @return Verdict|null
     */
    public function verdict(?string $attribute = null): ?Verdict
    {
        if ($attribute !== null) {
            return $this->verdicts[$attribute] ?? null;
        }

        return $this->verdicts === [] ? null : end($this->verdicts);
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @param Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        unset($this->verdicts[$attribute]);

        // Empty is `required`'s job, and checking nothing costs a call.
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        try {
            $verdict = app('laratox.client')->{$this->kind}($value, $this->options);

            // Read the decision here, so a missing or unknown one is an outage (the SDK
            // throws when it is read), not an exception in the middle of validation.
            if (! array_key_exists('decision', $verdict->raw)) {
                throw new ApiError('ToxicFilter answered without a decision.', 0);
            }

            $refused = $verdict->blocked() || ($this->refuseReview && $verdict->needsReview());
        } catch (ApiError $e) {
            $this->unavailable($attribute, $e, $fail);

            return;
        }

        $this->verdicts[$attribute] = $verdict;

        if (! $refused) {
            return;
        }

        $reason = $this->showsReason() ? $this->reason($verdict) : null;

        $reason === null
            ? $fail('laratox::messages.refused')->translate()
            : $fail('laratox::messages.refused_because')->translate(['reason' => $reason]);
    }

    /**
     * Reasons are written in English, so "auto" shows them only when the app speaks it.
     *
     * @return bool
     */
    private function showsReason(): bool
    {
        $setting = config('laratox.rule.show_reason', 'auto');

        if ($setting === 'auto') {
            return str_starts_with((string) app()->getLocale(), 'en');
        }

        return (bool) $setting;
    }

    /**
     * The first reason, ready for a sentence.
     *
     * @param Verdict $verdict
     * @return string|null
     */
    private function reason(Verdict $verdict): ?string
    {
        $reason = rtrim(trim((string) $verdict->reason()), '.');

        if ($reason === '') {
            return null;
        }

        // Lower the first letter of a word, not of an acronym ("IBAN" stays).
        return preg_match('/^\p{Lu}\p{Ll}/u', $reason) ? lcfirst($reason) : $reason;
    }

    /**
     * No verdict: log it, and fail the field only if `on_error` is "refuse".
     *
     * @param string $attribute
     * @param ApiError $error
     * @param Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     * @return void
     */
    private function unavailable(string $attribute, ApiError $error, Closure $fail): void
    {
        $refuse = config('laratox.rule.on_error', 'allow') === 'refuse';

        Log::warning('laratox: the field was not moderated.', [
            'attribute' => $attribute,
            'status' => $error->status,
            'code' => $error->errorCode,
            'message' => $error->getMessage(),
            'outcome' => $refuse ? 'refused' : 'allowed',
        ]);

        if ($refuse) {
            $fail('laratox::messages.unavailable')->translate();
        }
    }
}
