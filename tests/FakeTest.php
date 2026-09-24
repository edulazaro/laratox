<?php

namespace EduLazaro\Laratox\Tests;

use EduLazaro\Laratox\Facades\ToxicFilter;
use Illuminate\Support\Facades\Http;
use ToxicFilter\Client;
use ToxicFilter\Exception\NotFound;

class FakeTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * @return void
     */
    public function test_everything_is_allowed_by_default(): void
    {
        $fake = ToxicFilter::fake();

        $verdict = ToxicFilter::text('hello there')->reference('comment_1')->check();

        $this->assertTrue($verdict->allowed());
        $this->assertSame('comment_1', $verdict->reference());
        $fake->assertSent(fn (array $request) => $request['path'] === '/api/v1/text' && $request['body']['content'] === 'hello there');
        $fake->assertSentCount(1);
    }

    /**
     * @return void
     */
    public function test_it_blocks_and_holds_when_told(): void
    {
        $fake = ToxicFilter::fake()->shouldBlock('spam', 'Contains a referral link');

        $blocked = ToxicFilter::text('join via my link')->check();

        $this->assertTrue($blocked->blocked());
        $this->assertSame(['spam'], $blocked->flagged());
        $this->assertSame(0.95, $blocked->score('spam'));
        $this->assertSame(['Contains a referral link'], $blocked->reasons());

        $fake->shouldReview('toxicity');
        $this->assertTrue(ToxicFilter::text('meh')->check()->needsReview());

        $fake->shouldAllow();
        $this->assertTrue(ToxicFilter::text('meh')->check()->allowed());
    }

    /**
     * @return void
     */
    public function test_an_answer_can_apply_to_some_requests_only(): void
    {
        ToxicFilter::fake()->shouldBlock('spam', when: fn (array $request) => str_contains($request['body']['content'] ?? '', 'casino'));

        $this->assertTrue(ToxicFilter::text('best casino bonus')->check()->blocked());
        $this->assertTrue(ToxicFilter::text('good morning')->check()->allowed());
    }

    /**
     * @return void
     */
    public function test_an_injected_client_is_the_fake_too(): void
    {
        $fake = ToxicFilter::fake()->shouldBlock();

        $this->assertTrue(app(Client::class)->name('admin_official')->blocked());
        $fake->assertSent(fn (array $request) => $request['path'] === '/api/v1/name');
    }

    /**
     * @return void
     */
    public function test_a_batch_answers_every_item(): void
    {
        ToxicFilter::fake()->shouldBlock('spam', when: fn (array $request) => ($request['body']['content'] ?? '') === 'spam');

        $verdicts = ToxicFilter::batch([
            ['kind' => 'text', 'content' => 'hello'],
            ['kind' => 'text', 'content' => 'spam'],
        ])->verdicts();

        $this->assertTrue($verdicts[0]->allowed());
        $this->assertTrue($verdicts[1]->blocked());
    }

    /**
     * @return void
     */
    public function test_recent_batches_answer_an_empty_list(): void
    {
        ToxicFilter::fake();

        $this->assertSame([], ToxicFilter::batches());
    }

    /**
     * @return void
     */
    public function test_an_endpoint_it_does_not_model_is_a_404(): void
    {
        ToxicFilter::fake();

        $this->expectException(NotFound::class);

        ToxicFilter::records();
    }

    /**
     * @return void
     */
    public function test_nothing_sent_can_be_asserted(): void
    {
        ToxicFilter::fake()->assertNothingSent();
    }

    /**
     * The global alias and the SDK's `ToxicFilter\` namespace do not collide.
     *
     * @return void
     */
    public function test_the_alias_and_the_sdk_namespace_do_not_collide(): void
    {
        \ToxicFilter::fake()->shouldBlock();

        $this->assertTrue(\ToxicFilter::text('x')->check()->blocked());
        $this->assertTrue(app(\ToxicFilter\Client::class)->text('x')->blocked());
    }

    /**
     * More locales than the API accepts is refused, like the API does.
     *
     * @return void
     */
    public function test_too_many_locales_is_refused_like_the_api_does(): void
    {
        ToxicFilter::fake();

        ToxicFilter::text('hola')->locale('es', 'en', 'pt', 'fr', 'it', 'de', 'nl', 'ca', 'pl', 'ru')->check();

        $this->expectException(\ToxicFilter\Exception\InvalidRequest::class);

        ToxicFilter::text('hola')->locale('es', 'en', 'pt', 'fr', 'it', 'de', 'nl', 'ca', 'pl', 'ru', 'tr')->check();
    }
}
