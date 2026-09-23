<?php

namespace EduLazaro\Laratox\Tests;

use EduLazaro\Laratox\Facades\ToxicFilter;
use EduLazaro\Laratox\LaratoxServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * The package booted inside a bare Laravel application.
 *
 * The host is `toxicfilter.test` and retries are off, so a test that forgets
 * `Http::fake()` fails on a stray request instead of reaching anything, and a test
 * asserting an error does not sit through the SDK's growing wait.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LaratoxServiceProvider::class];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['ToxicFilter' => ToxicFilter::class];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('laratox.key', 'tf_test_0123456789abcdef');
        $app['config']->set('laratox.url', 'https://toxicfilter.test');
        $app['config']->set('laratox.retries', 0);
    }

    /**
     * A verdict body in the shape the API sends.
     *
     * @param string $decision
     * @param string|null $reason
     * @return array<string, mixed>
     */
    protected function verdictBody(string $decision = 'allow', ?string $reason = null): array
    {
        return [
            'id' => 'mod_01TEST',
            'reference' => null,
            'decision' => $decision,
            'flagged' => $reason ? ['spam'] : [],
            'scores' => $reason ? ['spam' => 0.9] : [],
            'signals' => $reason ? [['category' => 'spam', 'score' => 0.9, 'detector' => 'links', 'reason' => $reason, 'evidence' => []]] : [],
            'used_ai' => false,
            'took_ms' => 1,
            'cached' => false,
            'charged' => 1,
            'policy' => ['slug' => 'default', 'version' => 0],
            'credits' => ['remaining' => 1999, 'charged' => 1, 'renews_at' => null],
        ];
    }
}
