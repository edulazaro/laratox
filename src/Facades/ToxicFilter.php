<?php

namespace EduLazaro\Laratox\Facades;

use EduLazaro\Laratox\Laratox;
use EduLazaro\Laratox\Testing\FakeToxicFilter;
use Illuminate\Support\Facades\Facade;
use ToxicFilter\Client;

/**
 * ToxicFilter::text($body)->check();
 *
 * @method static \EduLazaro\Laratox\PendingCheck text(string $content)
 * @method static \EduLazaro\Laratox\PendingCheck name(string $name)
 * @method static \EduLazaro\Laratox\PendingCheck email(string $address)
 * @method static \EduLazaro\Laratox\PendingCheck url(string $url)
 * @method static \EduLazaro\Laratox\PendingCheck image(string $url)
 * @method static \EduLazaro\Laratox\PendingCheck imageData(string $bytes)
 * @method static \EduLazaro\Laratox\PendingCheck prompt(string $content)
 * @method static \EduLazaro\Laratox\PendingCheck conversation(array $messages)
 * @method static \EduLazaro\Laratox\PendingCheck signup(array $fields)
 * @method static \ToxicFilter\BatchResult batch(array $items, array $options = [])
 * @method static \ToxicFilter\BatchResult batchAsync(array $items, array $options = [])
 * @method static \ToxicFilter\BatchResult batchStatus(string $batchId, array $query = [])
 * @method static list<\ToxicFilter\BatchResult> batches(array $query = [])
 * @method static array records(array $query = [])
 * @method static \ToxicFilter\Verdict record(string $id)
 * @method static \ToxicFilter\Verdict resolve(string $id, string $action, string|null $moderator = null, string|null $note = null)
 * @method static \ToxicFilter\Verdict feedback(string $id, string $verdict, string|null $note = null)
 * @method static array usage()
 * @method static array ping()
 * @method static \ToxicFilter\Client client()
 *
 * @see \EduLazaro\Laratox\Laratox
 */
class ToxicFilter extends Facade
{
    /**
     * Answer from memory instead of the API. The SDK's real client still runs.
     *
     * @return FakeToxicFilter
     */
    public static function fake(): FakeToxicFilter
    {
        $fake = new FakeToxicFilter();
        $client = new Client('tf_test_fake', 'https://toxicfilter.test', retries: 0, transport: $fake);

        static::$app->instance('laratox.client', $client);
        static::swap(new Laratox($client));

        return $fake;
    }

    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'laratox';
    }
}
