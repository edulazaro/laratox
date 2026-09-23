<?php

namespace EduLazaro\Laratox;

use EduLazaro\Laratox\Commands\PingCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use ToxicFilter\Client;

/**
 * Registers the SDK client, the facade's target and the ping command.
 */
class LaratoxServiceProvider extends ServiceProvider
{
    /**
     * `laratox.client` is the SDK client (also injectable as `ToxicFilter\Client`);
     * `laratox` is what the facade uses.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laratox.php', 'laratox');

        $this->app->singleton('laratox.client', fn (Application $app) => $this->client($app));
        $this->app->alias('laratox.client', Client::class);

        $this->app->singleton('laratox', fn (Application $app) => new Laratox($app->make('laratox.client')));
        $this->app->alias('laratox', Laratox::class);
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'laratox');

        $this->publishes([__DIR__ . '/../config/laratox.php' => config_path('laratox.php')], 'laratox-config');
        $this->publishes([__DIR__ . '/../lang' => $this->app->langPath('vendor/laratox')], 'laratox-lang');

        if ($this->app->runningInConsole()) {
            $this->commands([PingCommand::class]);
        }
    }

    /**
     * The SDK client, over Laravel's HTTP client.
     *
     * @param Application $app
     * @return Client
     *
     * @throws RuntimeException When TOXICFILTER_KEY is not set.
     */
    private function client(Application $app): Client
    {
        $config = (array) $app['config']->get('laratox');

        if (empty($config['key'])) {
            throw new RuntimeException('laratox: set TOXICFILTER_KEY in your .env (a tf_test_ key works while you integrate).');
        }

        return new Client(
            key: (string) $config['key'],
            baseUrl: (string) $config['url'],
            retries: (int) $config['retries'],
            transport: new HttpTransport(
                $app->make(Factory::class),
                timeout: (int) $config['timeout'],
                connectTimeout: (int) $config['connect_timeout'],
            ),
        );
    }
}
