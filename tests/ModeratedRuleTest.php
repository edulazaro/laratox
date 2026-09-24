<?php

namespace EduLazaro\Laratox\Tests;

use EduLazaro\Laratox\Facades\ToxicFilter;
use EduLazaro\Laratox\Rules\Moderated;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * `Moderated`: a field judged by ToxicFilter as part of validation.
 */
class ModeratedRuleTest extends TestCase
{
    /**
     * @param mixed $value
     * @param Moderated $rule
     * @return \Illuminate\Validation\Validator
     */
    private function validate(mixed $value, Moderated $rule): \Illuminate\Validation\Validator
    {
        return Validator::make(['comment' => $value], ['comment' => [$rule]]);
    }

    /**
     * @return void
     */
    public function test_an_allowed_field_passes_and_keeps_the_verdict(): void
    {
        ToxicFilter::fake();

        $rule = Moderated::text();

        $this->assertTrue($this->validate('good morning', $rule)->passes());
        $this->assertTrue($rule->verdict()->allowed());
    }

    /**
     * A block fails the field, with ToxicFilter's reason in the sentence.
     *
     * @return void
     */
    public function test_a_blocked_field_fails_with_the_reason(): void
    {
        ToxicFilter::fake()->shouldBlock('personal_data', 'Contains a phone number.');

        $validator = $this->validate('call me on 600 000 000', Moderated::text());

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'The comment could not be accepted: contains a phone number.',
            $validator->errors()->first('comment'),
        );
    }

    /**
     * An acronym at the start keeps its capitals.
     *
     * @return void
     */
    public function test_a_reason_starting_with_an_acronym_is_not_lowered(): void
    {
        ToxicFilter::fake()->shouldBlock('personal_data', 'IBAN in the text');

        $this->assertSame(
            'The comment could not be accepted: IBAN in the text.',
            $this->validate('ES91 2100 0418 4502 0005 1332', Moderated::text())->errors()->first('comment'),
        );
    }

    /**
     * @return void
     */
    public function test_the_reason_can_be_kept_out_of_the_message(): void
    {
        config(['laratox.rule.show_reason' => false]);
        ToxicFilter::fake()->shouldBlock();

        $this->assertSame(
            'The comment could not be accepted.',
            $this->validate('x', Moderated::text())->errors()->first('comment'),
        );
    }

    /**
     * Review means a person should look, not that the author should be told no, unless
     * the form says there is nobody to look.
     *
     * @return void
     */
    public function test_review_passes_unless_asked_to_refuse_it(): void
    {
        ToxicFilter::fake()->shouldReview('toxicity', 'Contempt aimed at the reader');

        $rule = Moderated::text();

        $this->assertTrue($this->validate('you again?', $rule)->passes());
        $this->assertTrue($rule->verdict()->needsReview());

        $this->assertTrue($this->validate('you again?', Moderated::text()->orReview())->fails());
    }

    /**
     * An empty value is `required`'s question, and asking about nothing costs a call.
     *
     * @return void
     */
    public function test_an_empty_field_is_not_sent(): void
    {
        $fake = ToxicFilter::fake();

        $this->assertTrue($this->validate('   ', Moderated::text())->passes());
        $this->assertTrue($this->validate(null, Moderated::text())->passes());

        $fake->assertNothingSent();
    }

    /**
     * The options reach the API unchanged, and the kind picks the endpoint.
     *
     * @return void
     */
    public function test_the_kind_and_the_options_reach_the_api(): void
    {
        $fake = ToxicFilter::fake();

        $this->validate('https://paypal.com@evil.example', Moderated::url()->reference('profile_7'))->passes();

        $fake->assertSent(fn (array $request) => $request['path'] === '/api/v1/url'
            && $request['body']['url'] === 'https://paypal.com@evil.example'
            && $request['body']['reference'] === 'profile_7');
    }

    /**
     * An outage does not take the form down: the field passes and a warning is logged.
     *
     * @return void
     */
    public function test_an_unanswered_call_passes_and_logs_by_default(): void
    {
        Http::fake(['toxicfilter.test/*' => Http::response(['error' => ['code' => 'server_error']], 503)]);
        Log::spy();

        $rule = Moderated::text();

        $this->assertTrue($this->validate('hola', $rule)->passes());
        $this->assertNull($rule->verdict());

        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * Where nothing may be published unchecked, an outage fails the field instead.
     *
     * @return void
     */
    public function test_an_unanswered_call_can_refuse_the_field(): void
    {
        config(['laratox.rule.on_error' => 'refuse']);
        Http::fake(['toxicfilter.test/*' => Http::response(['error' => ['code' => 'server_error']], 503)]);

        $validator = $this->validate('hola', Moderated::text());

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('could not be checked', $validator->errors()->first('comment'));
    }

    /**
     * A 2xx or 3xx with no decision in it is not an answer. Read as one it would be an
     * `allow`, and a proxy page or a redirect would publish whatever was sent.
     *
     * @return void
     */
    public function test_a_body_with_no_decision_is_not_an_allow(): void
    {
        config(['laratox.rule.on_error' => 'refuse']);
        Http::fake(['toxicfilter.test/*' => Http::response('', 301, ['Location' => 'https://elsewhere.test/'])]);

        $rule = Moderated::text();

        $this->assertTrue($this->validate('hola', $rule)->fails());
        $this->assertNull($rule->verdict());
    }

    /**
     * @return void
     */
    public function test_a_kind_one_field_cannot_be_judged_as_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Moderated('signup');
    }

    /**
     * A decision the SDK does not know is an outage, not an exception out of validation.
     *
     * @return void
     */
    public function test_an_unknown_decision_follows_on_error(): void
    {
        config(['laratox.rule.on_error' => 'refuse']);
        ToxicFilter::fake()->respondWith(['decision' => 'hold']);

        $rule = Moderated::text();

        $this->assertTrue($this->validate('hola', $rule)->fails());
        $this->assertNull($rule->verdict());
    }

    /**
     * The rule chains the same options as the facade.
     *
     * @return void
     */
    public function test_the_rule_chains_the_same_options_as_the_facade(): void
    {
        $fake = ToxicFilter::fake();

        $this->validate('hola', Moderated::text()->policy('comments')->locale('es')->withoutAi())->passes();

        $fake->assertSent(fn (array $request) => $request['body'] === [
            'content' => 'hola',
            'policy' => 'comments',
            'locales' => ['es'],
            'ai' => false,
        ]);
    }

    /**
     * Reasons are in English, so by default a Spanish app shows the message without one.
     *
     * @return void
     */
    public function test_a_non_english_app_gets_no_english_reason_by_default(): void
    {
        app()->setLocale('es');
        ToxicFilter::fake()->shouldBlock('personal_data', 'Contains a phone number');

        $this->assertSame(
            'El campo comment no se puede aceptar.',
            $this->validate('600 000 000', Moderated::text())->errors()->first('comment'),
        );
    }

    /**
     * One rule over several fields keeps a verdict per field.
     *
     * @return void
     */
    public function test_a_wildcard_rule_keeps_a_verdict_per_field(): void
    {
        ToxicFilter::fake()->shouldBlock('spam', when: fn (array $request) => ($request['body']['content'] ?? '') === 'spam');

        $rule = Moderated::text();
        $validator = Validator::make(['tags' => ['hello', 'spam']], ['tags.*' => [$rule]]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($rule->verdict('tags.0')->allowed());
        $this->assertTrue($rule->verdict('tags.1')->blocked());
        $this->assertTrue($rule->verdict()->blocked());
    }

    /**
     * The rule files under the configured project too.
     *
     * @return void
     */
    public function test_the_rule_uses_the_configured_project(): void
    {
        config(['laratox.project' => 'forum']);
        $fake = \EduLazaro\Laratox\Facades\ToxicFilter::fake();

        validator(['body' => 'hola'], ['body' => [\EduLazaro\Laratox\Rules\Moderated::text()]])->passes();

        $this->assertSame('forum', $fake->sent()[0]['body']['project']);
    }
}
