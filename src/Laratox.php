<?php

namespace EduLazaro\Laratox;

use ToxicFilter\Client;

/**
 * What the `ToxicFilter` facade resolves to.
 *
 * Content methods start a `PendingCheck`. Everything else goes straight to the SDK.
 *
 * @mixin Client
 */
class Laratox
{
    /**
     * @param Client $client
     */
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * A comment, a message, a description.
     *
     * @param string $content
     * @return PendingCheck
     */
    public function text(string $content): PendingCheck
    {
        return $this->pending('text', $content);
    }

    /**
     * A display name or username.
     *
     * @param string $name
     * @return PendingCheck
     */
    public function name(string $name): PendingCheck
    {
        return $this->pending('name', $name);
    }

    /**
     * An email address.
     *
     * @param string $address
     * @return PendingCheck
     */
    public function email(string $address): PendingCheck
    {
        return $this->pending('email', $address);
    }

    /**
     * A link. Never fetched.
     *
     * @param string $url
     * @return PendingCheck
     */
    public function url(string $url): PendingCheck
    {
        return $this->pending('url', $url);
    }

    /**
     * An image, by URL.
     *
     * @param string $url
     * @return PendingCheck
     */
    public function image(string $url): PendingCheck
    {
        return $this->pending('image', $url);
    }

    /**
     * An image, as raw bytes, base64 or a data URI.
     *
     * @param string $bytes
     * @return PendingCheck
     */
    public function imageData(string $bytes): PendingCheck
    {
        return $this->pending('imageData', $bytes);
    }

    /**
     * Text going into your own model, checked for prompt injection.
     *
     * @param string $content
     * @return PendingCheck
     */
    public function prompt(string $content): PendingCheck
    {
        return $this->pending('prompt', $content);
    }

    /**
     * A thread, oldest first. The last message is judged.
     *
     * @param list<array{author: string, content: string}> $messages
     * @return PendingCheck
     */
    public function conversation(array $messages): PendingCheck
    {
        return $this->pending('conversation', $messages);
    }

    /**
     * A signup: name, email and bio, together.
     *
     * @param array<string, string> $fields
     * @return PendingCheck
     */
    public function signup(array $fields): PendingCheck
    {
        return $this->pending('signup', $fields);
    }

    /**
     * The SDK client itself.
     *
     * @return Client
     */
    public function client(): Client
    {
        return $this->client;
    }

    /**
     * Anything else goes to the SDK unchanged: batch(), records(), usage(), ping()…
     *
     * A batch is the one exception: it is filed under `laratox.project` like a check,
     * unless its options name another.
     *
     * @param string $method
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments): mixed
    {
        $project = config('laratox.project');

        if (in_array($method, ['batch', 'batchAsync'], true) && is_string($project) && $project !== '') {
            $arguments[1] = ['project' => $project] + (array) ($arguments[1] ?? []);
        }

        return $this->client->{$method}(...$arguments);
    }

    /**
     * @param string $kind
     * @param string|array<int|string, mixed> $subject
     * @return PendingCheck
     */
    private function pending(string $kind, string|array $subject): PendingCheck
    {
        return new PendingCheck($this->client, $kind, $subject);
    }
}
