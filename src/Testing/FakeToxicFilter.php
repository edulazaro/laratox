<?php

namespace EduLazaro\Laratox\Testing;

use PHPUnit\Framework\Assert;
use ToxicFilter\Transport;

/**
 * Answers from memory. Installed by `ToxicFilter::fake()` under the SDK's real client.
 *
 *   $fake = ToxicFilter::fake();                  // everything allowed
 *   $fake->shouldBlock('spam', 'Contains a link');
 *   $fake->shouldReview('toxicity', when: fn ($request) => ...);
 *   $fake->assertSentCount(1);
 */
class FakeToxicFilter implements Transport
{
    /** Endpoints that answer with a verdict. */
    private const VERDICTS = ['text', 'email', 'name', 'signup', 'image', 'prompt', 'url', 'conversation'];

    /** @var list<array{decision: string, category: string, reason: string, score: float, when: callable|null}> */
    private array $answers = [];

    /** @var list<array{method: string, path: string, query: array<string, mixed>, body: array<string, mixed>|null, headers: array<string, string>}> */
    private array $sent = [];

    /** @var array<string, mixed>|null */
    private ?array $raw = null;

    private int $count = 0;

    /**
     * Allow everything again.
     *
     * @return static
     */
    public function shouldAllow(): static
    {
        $this->answers = [];
        $this->raw = null;

        return $this;
    }

    /**
     * Answer `review`, for every request or only those `$when` accepts.
     *
     * @param string $category
     * @param string $reason
     * @param float $score
     * @param (callable(array<string, mixed>): bool)|null $when
     * @return static
     */
    public function shouldReview(string $category = 'toxicity', string $reason = 'Held for review by the fake.', float $score = 0.6, ?callable $when = null): static
    {
        return $this->answer('review', $category, $reason, $score, $when);
    }

    /**
     * Answer `block`, for every request or only those `$when` accepts.
     *
     * @param string $category
     * @param string $reason
     * @param float $score
     * @param (callable(array<string, mixed>): bool)|null $when
     * @return static
     */
    public function shouldBlock(string $category = 'toxicity', string $reason = 'Refused by the fake.', float $score = 0.95, ?callable $when = null): static
    {
        return $this->answer('block', $category, $reason, $score, $when);
    }

    /**
     * Merge these fields into every verdict.
     *
     * @param array<string, mixed> $verdict
     * @return static
     */
    public function respondWith(array $verdict): static
    {
        $this->raw = $verdict;

        return $this;
    }

    /**
     * Every request received, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    /**
     * @param (callable(array<string, mixed>): bool)|null $callback
     * @return void
     */
    public function assertSent(?callable $callback = null): void
    {
        Assert::assertNotSame([], $this->matching($callback), $callback ? 'No request to ToxicFilter matched.' : 'Nothing was sent to ToxicFilter.');
    }

    /**
     * @param callable(array<string, mixed>): bool $callback
     * @return void
     */
    public function assertNotSent(callable $callback): void
    {
        Assert::assertSame([], $this->matching($callback), 'A request to ToxicFilter matched and should not have.');
    }

    /**
     * @return void
     */
    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->sent, count($this->sent) . ' request(s) were sent to ToxicFilter.');
    }

    /**
     * @param int $count
     * @return void
     */
    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent, 'ToxicFilter was called a different number of times.');
    }

    /**
     * Record the request and answer it.
     *
     * @param string $method
     * @param string $url
     * @param array<string, string> $headers
     * @param string|null $body
     * @return array{status: int, body: string}
     */
    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $request = [
            'method' => $method,
            'path' => (string) parse_url($url, PHP_URL_PATH),
            'query' => $query,
            'body' => $body === null ? null : (array) json_decode($body, true),
            'headers' => $headers,
        ];

        $this->sent[] = $request;

        [$status, $answer] = $this->respond($request);

        return ['status' => $status, 'body' => (string) json_encode($answer)];
    }

    /**
     * @param string $decision
     * @param string $category
     * @param string $reason
     * @param float $score
     * @param callable|null $when
     * @return static
     */
    private function answer(string $decision, string $category, string $reason, float $score, ?callable $when): static
    {
        $this->answers[] = compact('decision', 'category', 'reason', 'score', 'when');

        return $this;
    }

    /**
     * What the API would answer. Unknown endpoints get a 404.
     *
     * @param array<string, mixed> $request
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function respond(array $request): array
    {
        $endpoint = substr($request['path'], strlen('/api/v1/'));

        return match (true) {
            in_array($endpoint, self::VERDICTS, true) => [200, $this->verdict($request) + ['credits' => $this->credits()]],
            $endpoint === 'batch' => [200, $this->batch($request)],
            $endpoint === 'batches' => [200, ['batches' => []]],
            $endpoint === 'ping' => [200, ['ok' => true, 'version' => 'v1', 'mode' => 'test', 'time' => date(DATE_ATOM)]],
            $endpoint === 'usage' => [200, ['credits' => $this->credits() + ['allowance' => 2000]]],
            default => [404, ['error' => ['code' => 'not_found', 'message' => "The fake does not answer {$request['path']}."]]],
        };
    }

    /**
     * One verdict, in the API's shape.
     *
     * @param array<string, mixed> $request
     * @param array<string, mixed>|null $item A batch item, when answering one.
     * @return array<string, mixed>
     */
    private function verdict(array $request, ?array $item = null): array
    {
        $fields = $item ?? (array) ($request['body'] ?? []);
        $answer = $this->pick(['body' => $fields] + $request);

        $verdict = [
            'id' => 'mod_fake' . str_pad((string) ++$this->count, 6, '0', STR_PAD_LEFT),
            'reference' => $fields['reference'] ?? null,
            'decision' => $answer['decision'] ?? 'allow',
            'flagged' => $answer ? [$answer['category']] : [],
            'scores' => $answer ? [$answer['category'] => $answer['score']] : [],
            'signals' => $answer ? [[
                'category' => $answer['category'],
                'score' => $answer['score'],
                'detector' => 'fake',
                'reason' => $answer['reason'],
                'evidence' => [],
            ]] : [],
            'used_ai' => false,
            'took_ms' => 0,
            'cached' => false,
            'charged' => 0,
            'policy' => ['slug' => 'default', 'version' => 0],
        ];

        return $this->raw === null ? $verdict : array_replace($verdict, $this->raw);
    }

    /**
     * A sync batch: one verdict per item, with its index.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function batch(array $request): array
    {
        $items = array_values((array) ($request['body']['items'] ?? []));
        $results = [];

        foreach ($items as $index => $item) {
            $results[] = ['index' => $index] + $this->verdict($request, (array) $item);
        }

        return [
            'batch_id' => 'bat_fake',
            'status' => 'completed',
            'count' => count($items),
            'processed' => count($items),
            'failed' => 0,
            'credits_charged' => 0,
            'results' => $results,
            'credits' => $this->credits(),
        ];
    }

    /**
     * The latest answer that applies to this request, or null to allow it.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>|null
     */
    private function pick(array $request): ?array
    {
        foreach (array_reverse($this->answers) as $answer) {
            if ($answer['when'] === null || ($answer['when'])($request)) {
                return $answer;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function credits(): array
    {
        return ['remaining' => 2000, 'charged' => 0, 'renews_at' => null];
    }

    /**
     * @param (callable(array<string, mixed>): bool)|null $callback
     * @return list<array<string, mixed>>
     */
    private function matching(?callable $callback): array
    {
        return array_values(array_filter($this->sent, fn (array $request) => $callback === null || $callback($request)));
    }
}
