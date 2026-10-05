<?php

namespace EduLazaro\Laratox\Concerns;

/**
 * The options of a check, chainable. Shared by `PendingCheck` and the `Moderated` rule.
 */
trait ConfiguresCheck
{
    /** @var array<string, mixed> */
    protected array $options = [];

    /**
     * Use one of your policies instead of the default.
     *
     * @param string $slug
     * @return static
     */
    public function policy(string $slug): static
    {
        return $this->option('policy', $slug);
    }

    /**
     * File the verdict under one of your projects instead of `laratox.project`.
     *
     * @param string $slug
     * @return static
     */
    public function project(string $slug): static
    {
        return $this->option('project', $slug);
    }

    /**
     * Start from the configured project, if there is one.
     *
     * @return void
     */
    protected function useDefaultProject(): void
    {
        $project = config('laratox.project');

        if (is_string($project) && $project !== '') {
            $this->options['project'] = $project;
        }
    }

    /**
     * The languages it should be written in.
     *
     * @param string ...$locales
     * @return static
     */
    public function locale(string ...$locales): static
    {
        return $this->option('locales', $locales);
    }

    /**
     * Where it will appear: comment, listing, profile, review, job…
     *
     * @param string $surface
     * @return static
     */
    public function surface(string $surface): static
    {
        return $this->option('surface', $surface);
    }

    /**
     * Your own id for it, returned in the verdict and in webhooks.
     *
     * @param string $reference
     * @return static
     */
    public function reference(string $reference): static
    {
        return $this->option('reference', $reference);
    }

    /**
     * Who wrote it, in your own ids.
     *
     * @param string $actor
     * @return static
     */
    public function actor(string $actor): static
    {
        return $this->option('actor', $actor);
    }

    /**
     * How far the check may go: `low` (instant checks only, one credit), `medium` or `high`.
     *
     * @param string $effort
     * @return static
     */
    public function effort(string $effort): static
    {
        return $this->option('effort', $effort);
    }

    /**
     * Rules for this call only, instead of a stored policy.
     *
     * @param array<string, mixed> $rules
     * @return static
     */
    public function rules(array $rules): static
    {
        return $this->option('rules', $rules);
    }

    /**
     * Return the content with personal data masked.
     *
     * @return static
     */
    public function redact(): static
    {
        return $this->option('redact', true);
    }

    /**
     * What your site does with the content if it is refused, for the statement of
     * reasons: `removal`, `disabled`, `demoted` or `visibility`, plus `account_suspended`.
     *
     * @param string ...$measures
     * @return static
     */
    public function restriction(string ...$measures): static
    {
        return $this->option('restriction', count($measures) === 1 ? $measures[0] : $measures);
    }

    /**
     * Any other field the endpoint accepts.
     *
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function option(string $key, mixed $value): static
    {
        $this->options[$key] = $value;

        return $this;
    }
}
