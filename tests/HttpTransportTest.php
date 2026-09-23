<?php

namespace EduLazaro\Laratox\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ToxicFilter\Client;
use ToxicFilter\Exception\QuotaExhausted;
use ToxicFilter\Exception\ServerError;

/**
 * The SDK's calls, carried by Laravel's HTTP client.
 */
class HttpTransportTest extends TestCase
{
    /**
     * A call goes through `Http::`, so the application's own fakes see it, with the key,
     * the idempotency key and the body the SDK built.
     *
     * @return void
     */
    public function test_a_call_goes_through_the_applications_http_client(): void
    {
        Http::fake(['toxicfilter.test/*' => Http::response($this->verdictBody('block', 'Contains a referral link'))]);

        $verdict = app(Client::class)->text('buy followers here', ['locales' => ['en']]);

        $this->assertTrue($verdict->blocked());
        $this->assertSame(['Contains a referral link'], $verdict->reasons());

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://toxicfilter.test/api/v1/text'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer tf_test_0123456789abcdef')
                && $request->hasHeader('Idempotency-Key')
                && $request['content'] === 'buy followers here'
                && $request['locales'] === ['en'];
        });
    }

    /**
     * A connection that never opens is the SDK's own retryable failure, not a Laravel
     * exception the client has never heard of.
     *
     * @return void
     */
    public function test_an_unreachable_service_is_a_server_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        try {
            app(Client::class)->text('hola');
            $this->fail('An unreachable service was not reported.');
        } catch (ServerError $e) {
            $this->assertSame(0, $e->status);
            $this->assertStringContainsString('Connection refused', $e->getMessage());
        }
    }

    /**
     * A 402 is not retried, and nothing underneath retries it either: the transport adds
     * no retry of its own, so one call is one request.
     *
     * @return void
     */
    public function test_a_402_is_asked_once(): void
    {
        config(['laratox.retries' => 2]);
        $this->app->forgetInstance('laratox.client');

        Http::fake(['toxicfilter.test/*' => Http::response([
            'error' => ['code' => 'quota_exhausted', 'message' => 'Out of credits.'],
            'credits' => ['remaining' => 3, 'required' => 8, 'renews_at' => null],
        ], 402)]);

        try {
            app(Client::class)->text('hola');
            $this->fail('A 402 was not reported.');
        } catch (QuotaExhausted $e) {
            $this->assertSame(8, $e->required());
        }

        Http::assertSentCount(1);
    }

    /**
     * A redirect is handed back as a redirect, and the SDK refuses it rather than read it
     * as an answer. Following it would send the key to an address nobody configured.
     *
     * @return void
     */
    public function test_a_redirect_is_not_followed(): void
    {
        Http::fake([
            'toxicfilter.test/api/v1/text' => Http::response('', 301, ['Location' => 'https://elsewhere.test/api/v1/text']),
            'elsewhere.test/*' => Http::response($this->verdictBody()),
        ]);

        try {
            app(Client::class)->text('hola');
            $this->fail('A redirect was read as an answer.');
        } catch (ServerError $e) {
            $this->assertSame(301, $e->status);
        }

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'elsewhere.test'));
    }
}
