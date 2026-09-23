<?php

namespace EduLazaro\Laratox\Tests;

use EduLazaro\Laratox\Facades\ToxicFilter;
use EduLazaro\Laratox\Laratox;
use RuntimeException;
use ToxicFilter\Client;

class ServiceProviderTest extends TestCase
{
    /**
     * One SDK client, injectable, and one Laratox behind the facade using it.
     *
     * @return void
     */
    public function test_the_client_and_the_facade_are_wired(): void
    {
        $client = app(Client::class);

        $this->assertSame($client, app('laratox.client'));
        $this->assertInstanceOf(Laratox::class, ToxicFilter::getFacadeRoot());
        $this->assertSame($client, ToxicFilter::client());
    }

    /**
     * @return void
     */
    public function test_a_missing_key_is_refused_with_a_sentence(): void
    {
        config(['laratox.key' => null]);
        $this->app->forgetInstance('laratox.client');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TOXICFILTER_KEY');

        app('laratox.client');
    }
}
