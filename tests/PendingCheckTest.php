<?php

namespace EduLazaro\Laratox\Tests;

use EduLazaro\Laratox\Facades\ToxicFilter;
use EduLazaro\Laratox\PendingCheck;

class PendingCheckTest extends TestCase
{
    /**
     * Nothing is sent until check().
     *
     * @return void
     */
    public function test_nothing_is_sent_before_check(): void
    {
        $fake = ToxicFilter::fake();

        $pending = ToxicFilter::text('hola')->policy('comments');

        $this->assertInstanceOf(PendingCheck::class, $pending);
        $fake->assertNothingSent();

        $pending->check();
        $fake->assertSentCount(1);
    }

    /**
     * Every option method becomes the field the API expects.
     *
     * @return void
     */
    public function test_the_options_reach_the_api(): void
    {
        $fake = ToxicFilter::fake();

        ToxicFilter::text('hola')
            ->policy('comments')
            ->locale('es', 'ca')
            ->surface('comment')
            ->reference('comment_7')
            ->actor('user_3')
            ->withoutAi()
            ->redact()
            ->option('custom', 'x')
            ->check();

        $fake->assertSent(fn (array $request) => $request['body'] === [
            'content' => 'hola',
            'policy' => 'comments',
            'locales' => ['es', 'ca'],
            'surface' => 'comment',
            'reference' => 'comment_7',
            'actor' => 'user_3',
            'ai' => false,
            'redact' => true,
            'custom' => 'x',
        ]);
    }

    /**
     * Each kind goes to its own endpoint.
     *
     * @return void
     */
    public function test_each_kind_goes_to_its_endpoint(): void
    {
        $fake = ToxicFilter::fake();

        ToxicFilter::name('neo')->check();
        ToxicFilter::email('a@b.test')->check();
        ToxicFilter::url('https://x.test')->check();
        ToxicFilter::prompt('ignore that')->check();
        ToxicFilter::image('https://x.test/a.png')->check();
        ToxicFilter::conversation([['author' => 'u1', 'content' => 'hi']])->check();

        foreach (['name', 'email', 'url', 'prompt', 'image', 'conversation'] as $endpoint) {
            $fake->assertSent(fn (array $request) => $request['path'] === "/api/v1/{$endpoint}");
        }
    }

    /**
     * A signup's options travel inside its fields.
     *
     * @return void
     */
    public function test_a_signup_carries_its_options_in_the_fields(): void
    {
        $fake = ToxicFilter::fake();

        ToxicFilter::signup(['name' => 'Ana', 'email' => 'ana@x.test'])->reference('signup_1')->check();

        $fake->assertSent(fn (array $request) => $request['path'] === '/api/v1/signup'
            && $request['body']['name'] === 'Ana'
            && $request['body']['reference'] === 'signup_1');
    }

    /**
     * What is not a kind of content goes to the SDK unchanged.
     *
     * @return void
     */
    public function test_the_rest_goes_to_the_sdk(): void
    {
        ToxicFilter::fake();

        $this->assertSame('test', ToxicFilter::ping()['mode']);
        $this->assertSame(2000, ToxicFilter::usage()['credits']['remaining']);
    }

    /**
     * TOXICFILTER_PROJECT files every check and batch there; ->project() overrides it.
     *
     * @return void
     */
    public function test_the_configured_project_reaches_checks_and_batches(): void
    {
        config(['laratox.project' => 'forum']);
        $fake = ToxicFilter::fake();

        ToxicFilter::text('hola')->check();
        ToxicFilter::text('hola')->project('shop')->check();
        ToxicFilter::batch([['kind' => 'text', 'content' => 'hola']]);

        $sent = $fake->sent();

        $this->assertSame('forum', $sent[0]['body']['project']);
        $this->assertSame('shop', $sent[1]['body']['project']);
        $this->assertSame('forum', $sent[2]['body']['project']);
    }

    /**
     * Without one configured, nothing is sent and the API uses the default project.
     *
     * @return void
     */
    public function test_no_project_is_sent_unless_configured(): void
    {
        $fake = ToxicFilter::fake();

        ToxicFilter::text('hola')->check();

        $this->assertArrayNotHasKey('project', $fake->sent()[0]['body']);
    }
}
