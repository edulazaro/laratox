<?php

namespace EduLazaro\Laratox\Tests;

use EduLazaro\Laratox\Facades\ToxicFilter;
use Illuminate\Support\Facades\Http;

/**
 * `php artisan laratox:ping`.
 */
class PingCommandTest extends TestCase
{
    /**
     * @return void
     */
    public function test_it_reports_the_key_and_the_credits(): void
    {
        ToxicFilter::fake();

        $this->artisan('laratox:ping')
            ->expectsOutputToContain('test (never charged)')
            ->expectsOutputToContain('2,000 of 2,000')
            ->assertSuccessful();
    }

    /**
     * A refused key comes back as the sentence that points at the fix.
     *
     * @return void
     */
    public function test_a_refused_key_says_which_variable_to_check(): void
    {
        Http::fake(['toxicfilter.test/*' => Http::response(['error' => ['code' => 'invalid_key', 'message' => 'Invalid API key.']], 401)]);

        $this->artisan('laratox:ping')
            ->expectsOutputToContain('TOXICFILTER_KEY')
            ->assertFailed();
    }

    /**
     * No key at all is reported, not thrown.
     *
     * @return void
     */
    public function test_no_key_is_reported(): void
    {
        config(['laratox.key' => '']);
        $this->app->forgetInstance('laratox.client');

        $this->artisan('laratox:ping')
            ->expectsOutputToContain('TOXICFILTER_KEY')
            ->assertFailed();
    }
}
