<?php

namespace EduLazaro\Laratox;

use EduLazaro\Laratox\Concerns\ConfiguresCheck;
use ToxicFilter\Client;
use ToxicFilter\Verdict;

/**
 * A check being built. Nothing is sent until `check()`.
 *
 *   ToxicFilter::text($body)->policy('comments')->locale('es')->check();
 */
class PendingCheck
{
    use ConfiguresCheck;

    /**
     * @param Client $client
     * @param string $kind The SDK method to call.
     * @param string|array<int|string, mixed> $subject
     */
    public function __construct(
        private readonly Client $client,
        private readonly string $kind,
        private readonly string|array $subject,
    ) {
        $this->useDefaultProject();
    }

    /**
     * Send it and return the verdict.
     *
     * @return Verdict
     *
     * @throws \ToxicFilter\Exception\ApiError
     */
    public function check(): Verdict
    {
        // The SDK's signup() takes one array, so the options travel inside it.
        if ($this->kind === 'signup') {
            return $this->client->signup($this->subject + $this->options);
        }

        return $this->client->{$this->kind}($this->subject, $this->options);
    }
}
