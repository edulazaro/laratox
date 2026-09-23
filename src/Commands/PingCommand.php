<?php

namespace EduLazaro\Laratox\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use ToxicFilter\Client;
use ToxicFilter\Exception\ApiError;

/**
 * php artisan laratox:ping
 *
 * Checks the key, the service and the credits left. Both calls are free.
 */
class PingCommand extends Command
{
    /** @var string */
    protected $signature = 'laratox:ping';

    /** @var string */
    protected $description = 'Check the ToxicFilter key, the service and the credits left';

    /**
     * @return int
     */
    public function handle(): int
    {
        try {
            /** @var Client $client */
            $client = $this->laravel->make('laratox.client');

            $ping = $client->ping();
            $credits = (array) ($client->usage()['credits'] ?? []);
        } catch (RuntimeException $e) {
            $this->components->error($e instanceof ApiError ? $this->explain($e) : $e->getMessage());

            return self::FAILURE;
        }

        $mode = (string) ($ping['mode'] ?? 'unknown');

        $this->components->info('ToxicFilter answered.');
        $this->components->twoColumnDetail('Key', $mode === 'test' ? 'test (never charged)' : $mode);
        $this->components->twoColumnDetail('API', (string) ($ping['version'] ?? '?'));
        $this->components->twoColumnDetail('Credits left', number_format((int) ($credits['remaining'] ?? 0)) . ' of ' . number_format((int) ($credits['allowance'] ?? 0)));
        $this->components->twoColumnDetail('Renews', (string) ($credits['renews_at'] ?? 'with the first charged call'));

        return self::SUCCESS;
    }

    /**
     * The failure, pointing at the fix.
     *
     * @param ApiError $error
     * @return string
     */
    private function explain(ApiError $error): string
    {
        return match (true) {
            $error->status === 401 => 'The key was refused. Check TOXICFILTER_KEY: it should start with tf_live_ or tf_test_.',
            $error->status === 0 => 'ToxicFilter could not be reached: ' . $error->getMessage(),
            default => "ToxicFilter answered {$error->status}: " . $error->getMessage(),
        };
    }
}
