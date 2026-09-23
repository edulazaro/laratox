<?php

namespace EduLazaro\Laratox;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use ToxicFilter\Exception\ServerError;
use ToxicFilter\Transport;

/**
 * Sends the SDK's requests through Laravel's HTTP client, so `Http::fake()` sees them.
 *
 * No retries (the SDK already retries, and never a 402) and no redirects.
 */
class HttpTransport implements Transport
{
    /**
     * @param Factory $http
     * @param int $timeout Seconds per attempt.
     * @param int $connectTimeout
     */
    public function __construct(
        private readonly Factory $http,
        private readonly int $timeout = 10,
        private readonly int $connectTimeout = 5,
    ) {
    }

    /**
     * @param string $method
     * @param string $url
     * @param array<string, string> $headers
     * @param string|null $body Raw JSON.
     * @return array{status: int, body: string}
     *
     * @throws ServerError When the connection fails, so the SDK retries it.
     */
    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $request = $this->http
            ->withHeaders($headers)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->withoutRedirecting();

        if ($body !== null) {
            $request = $request->withBody($body, $headers['Content-Type'] ?? 'application/json');
        }

        try {
            $response = $request->send($method, $url);
        } catch (ConnectionException $e) {
            throw new ServerError('Could not reach ToxicFilter: ' . $e->getMessage(), 0);
        }

        return ['status' => $response->status(), 'body' => $response->body()];
    }
}
