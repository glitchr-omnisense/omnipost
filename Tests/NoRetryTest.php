<?php

namespace Omnipost\Tests;

use Omnipost\Config;
use Omnipost\Model\Capabilities;
use Omnipost\Model\Post;
use Omnipost\Model\PostKind;
use Omnipost\Model\Publication;
use Omnipost\Model\PublicationState;
use Omnipost\Platform;
use Omnipost\ProviderFactory;
use Omnipost\ProviderInterface;
use Omnipost\PublisherInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The site's http_client with retry_failed on: Symfony's RetryableHttpClient
 * would publish the post again on a 503. The factory keeps its retries off.
 */
final class NoRetryTest extends TestCase
{
    /** @var list<string> */
    private array $sent = [];

    /** @var list<MockResponse> */
    private array $answers = [];

    public function testAPublishAnswered503IsSentOnceThroughARetryingClient(): void
    {
        $this->answers = [new MockResponse('{"error":"unavailable"}', ['http_code' => 503]), new MockResponse('{"id":"1"}')];
        $retrying = new RetryableHttpClient($this->transport(), new GenericRetryStrategy(GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES, 0), 3);
        $provider = (new HttpPublisherFactory($retrying))->create();
        \assert($provider instanceof HttpPublisher);

        self::assertSame(PublicationState::FAILED, $provider->publish(HttpPublisher::post())->state);
        self::assertSame(['POST https://social.example/api/v1/statuses'], $this->sent, 'the 503 was not retried by the client');
        self::assertCount(1, $this->answers, 'the second answer was never asked for');
    }

    public function testAMockHttpClientIsKeptAsGiven(): void
    {
        $mock = new MockHttpClient([new MockResponse('{"id":"1"}')]);
        $provider = (new HttpPublisherFactory($mock))->create();
        \assert($provider instanceof HttpPublisher);

        self::assertSame($mock, $provider->http);
        $provider->publish(HttpPublisher::post());
        self::assertSame(1, $mock->getRequestsCount());
    }

    private function transport(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->sent[] = $method.' '.$url;

            return array_shift($this->answers) ?? new MockResponse('{}', ['http_code' => 500]);
        });
    }
}

/** A network over HTTP: its provider publishes through the factory's client. */
final class HttpPublisherFactory extends ProviderFactory
{
    protected function populate(Config $config): void
    {
        $config->defaults(['omnipost.factory_name' => 'http']);
    }

    protected function build(Config $config): ProviderInterface
    {
        return new HttpPublisher($this->http ?? new MockHttpClient());
    }
}

final class HttpPublisher implements PublisherInterface
{
    public function __construct(public readonly HttpClientInterface $http)
    {
    }

    public static function post(): Post
    {
        return Post::image(\Omnipost\Model\Media::image('https://site.example/a.jpg'));
    }

    public function getName(): string
    {
        return 'http';
    }

    public function getPlatform(): Platform
    {
        return Platform::MASTODON;
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities([PostKind::IMAGE]);
    }

    public function publish(Post $post): Publication
    {
        $status = $this->http->request('POST', 'https://social.example/api/v1/statuses', ['json' => ['status' => 'x']])->getStatusCode();

        return new Publication(Platform::MASTODON, 200 === $status ? '1' : '', 200 === $status ? PublicationState::PUBLISHED : PublicationState::FAILED);
    }

    public function status(string $publicationId): Publication
    {
        return new Publication(Platform::MASTODON, $publicationId, PublicationState::PUBLISHED);
    }

    public function delete(string $publicationId): void
    {
    }
}
