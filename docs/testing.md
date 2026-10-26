---
title: Testing
order: 60
---

# Testing

An application that uses Omnipost tests its own code, not the networks: the providers take the
HTTP client they call with, so a test hands them a `MockHttpClient` and the answers it wants, and
checks what was sent. The validator calls nothing and needs no double.

Omnipost ships no fake provider and no conformance suite: there is no `Testing\` namespace in the
package. The core's own stub (`Omnipost\Tests\StubFactory`) is in its `Tests/` directory, for its
development only.

## A test case

```php
use Omnipost\Exception\InvalidPostException;
use Omnipost\Instagram\InstagramProviderFactory;
use Omnipost\Model\Media;
use Omnipost\Model\Post;
use Omnipost\Model\PublicationState;
use Omnipost\Platform;
use Omnipost\Registry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PublishingTest extends TestCase
{
    public function testAReelGoesToInstagramAsAContainer(): void
    {
        $container = new MockResponse('{"id":"17900000000000001"}');
        $registry = new Registry([new InstagramProviderFactory(new MockHttpClient([$container]))], [
            'instagram' => ['factory' => 'instagram', 'options' => ['access_token' => 'test']],
        ]);

        $reel = Post::reel(Media::video('https://site.example/reel.mp4', null, 42.0, 1080, 1920), 'Tonight', null, ['violin']);
        $publication = $registry->get('instagram')->publish($reel->for(Platform::INSTAGRAM));

        self::assertSame(PublicationState::PENDING, $publication->state);
        self::assertSame('17900000000000001', $publication->id);
        self::assertSame('POST', $container->getRequestMethod());
        self::assertSame('https://graph.instagram.com/v23.0/me/media', $container->getRequestUrl());
        parse_str($container->getRequestOptions()['body'], $sent);
        self::assertSame(['media_type' => 'REELS', 'video_url' => 'https://site.example/reel.mp4', 'caption' => "Tonight\n\n#violin", 'share_to_feed' => 'true', 'access_token' => 'test'], $sent);
    }

    public function testALandscapeReelIsRefusedBeforeAnyRequest(): void
    {
        $http = new MockHttpClient([]);   // no answer: any request would fail the test
        $instagram = (new InstagramProviderFactory($http))->create(['access_token' => 'test']);

        try {
            $instagram->publish(Post::reel(Media::video('https://site.example/clip.mp4', null, 42.0, 1920, 1080)));
            self::fail('refused');
        } catch (InvalidPostException $e) {
            self::assertSame(['media[0].ratio'], array_map(static fn ($v) => $v->path, $e->violations));
        }
        self::assertSame(0, $http->getRequestsCount());
    }
}
```

```
$ vendor/bin/phpunit --bootstrap vendor/autoload.php PublishingTest.php
PHPUnit 11.5.57 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.27

..                                                                  2 / 2 (100%)

Time: 00:00.259, Memory: 8.00 MB

OK (2 tests, 7 assertions)
```

(run on 2026-10-26 on the packages' checkouts, no network)

- `MockResponse::getRequestMethod()`, `getRequestUrl()` and `getRequestOptions()` give what was
  sent: the container's fields for Instagram (a form body), the metadata for YouTube (a JSON body).
- A `MockHttpClient` built with an empty list fails on any request: a test that a refused post
  sends nothing needs no assertion of its own beyond `getRequestsCount()`.
- A multi-step flow (Instagram's container, then its status, then `media_publish`) takes a list of
  answers in order, or a callable that answers by URL, as the examples of
  [Publishing](usage-publishing.md) do.

## Errors as answers

The networks' documented error shapes, to give a mock:

| To test | Answer |
|---|---|
| Instagram, a dead token | `new MockResponse('{"error":{"message":"Error validating access token","type":"OAuthException","code":190}}', ['http_code' => 400])` |
| Instagram, too many calls | the same with `"code":4` |
| YouTube, a revoked refresh token | `new MockResponse('{"error":"invalid_grant","error_description":"Token has been expired or revoked."}', ['http_code' => 400])` on the token endpoint |
| YouTube, the quota | `new MockResponse('{"error":{"code":403,"message":"...","errors":[{"reason":"quotaExceeded"}]}}', ['http_code' => 403])` |
| unreachable | `new MockResponse('', ['error' => 'Could not resolve host'])` |

What each becomes: [Errors](errors.md#by-provider).

## A provider of the test's own

To test code that takes a `PublisherInterface` without any network, implement it:

```php
use Omnipost\Model\Capabilities;
use Omnipost\Model\Post;
use Omnipost\Model\PostKind;
use Omnipost\Model\Publication;
use Omnipost\Model\PublicationState;
use Omnipost\Platform;
use Omnipost\PublisherInterface;

final class RecordingPublisher implements PublisherInterface
{
    /** @var list<Post> */
    public array $published = [];

    public function getName(): string { return 'recording'; }
    public function getPlatform(): Platform { return Platform::MASTODON; }
    public function capabilities(): Capabilities { return new Capabilities([PostKind::REEL, PostKind::IMAGE]); }
    public function publish(Post $post): Publication
    {
        $this->published[] = $post;

        return new Publication(Platform::MASTODON, 'p'.count($this->published), PublicationState::PUBLISHED);
    }
    public function status(string $publicationId): Publication { return new Publication(Platform::MASTODON, $publicationId, PublicationState::PUBLISHED); }
    public function delete(string $publicationId): void {}
}
```

In Symfony, replace `http_client` with a `MockHttpClient` in the test environment
(`framework.http_client.mock_response_factory`), or a provider's service
(`omnipost.provider.<name>`) with `static::getContainer()->set(...)` before it is first used.

## The package's own tests

`docker compose run --rm omnipost test` runs every package's tests in the [harness](harness.md):
the core's (the registry, capabilities and ratios, posts and variants, tokens, the validator rule
by rule, the bundle, the plain-PHP run with no framework class loaded) and each provider's on
answers written in the shapes the networks document.
