---
title: Usage policies
order: 45
---

# Usage policies

Each provider declares what its network's terms allow, as data: `policy.json` beside its code
(named in its `composer.json`, `extra.omnisense.policy`), in the schema of
[omnisense/contracts](https://github.com/glitchr-omnisense/contracts) - rate limits, rights
(commercial use, caching, storage, redistribution, attribution), requirements (a key) and where
it was read (`provenance`, with `verifiedAt`). What the terms do not publish is `unknown` or
`null`, never a figure made up.

| Provider | Limits declared | Rights | Read |
|---|---|---|---|
| `instagram` | `publish`: 100 a day per account (Meta: "100 API-published posts within a 24-hour moving period", a carousel one post); the business use case limit (4,800 × the account's impressions a day) depends on the audience: in the notes, not declared | commercial allowed; caching unknown (the terms set no period); storage per the Platform Terms (kept while needed, then deleted); redistribution forbidden; no attribution required | 2026-11-10 |
| `youtube` | `publish`: 100 a day per Google Cloud project (videos.insert's own bucket); 10,000 units a day for the rest, by cost: `status` 1, `account` 1, `feed` 3, `delete` 50 | commercial allowed within the developer policies; caching allowed 30 days at most; storage per the policies; redistribution forbidden; YouTube credited as the source wherever its content shows | 2026-11-10 |

The share links (`Omnipost\Share\`) call no API: each is a link to the network's own share page,
followed by the visitor. They have no policy to declare.

## Through the registry

```php
$registry = new Registry($factories, [
    'instagram' => [
        'factory' => 'instagram',
        'options' => ['access_token' => $token],
        // The site's own tightening: never looser than the provider's (omnisense/contracts' tighten()).
        'policy' => ['rateLimits' => [['max' => 50, 'window' => 'day', 'scope' => 'key', 'operations' => ['publish']]]],
        // What the site is: a use the terms forbid fails at once (UsageNotPermitted).
        'usage' => ['commercial' => true],
    ],
], $limiter); // a shared RateLimiterInterface when the site runs in several processes

$instagram = $registry->publisher('instagram');      // PublisherInterface behind the guard
$instagram->publish($post->for(Platform::INSTAGRAM)); // UsageLimitReachedException over the limit, nothing sent
$registry->feed('youtube')->feed();                   // FeedInterface behind the guard
$registry->policies();                                // every provider's policy, for a credits page
```

`publisher()` and `feed()` give the provider behind its guard: each call an operation named after
its method (`publish`, `status`, `delete`, `account`, `feed`), checked before (a limit reached
refuses it with nothing sent), counted after under the configured name (a limit scoped per `key`
counts per account). A call that sent nothing - a post the capabilities refuse, a credential
missing, a wait still running - is not counted; any other failure is, since the network may have
taken it. `get()`, `all()`, `publishers()` and `feeds()` give the providers themselves, unguarded,
as before; `publisher($name, $overrides)` builds a fresh one as `create()` does (a token from the
database), counted under the same name.

## Waits the network asks for

Each provider's API client is given its wait (`Config::backoff()`): while a Retry-After, a
RateLimit field with nothing left, or Instagram's `estimated_time_to_regain_access` lasts, every
call is refused before it is sent (`UsageLimitReachedException`, with `retryAfter`). The refusal
that set it is a `RateLimitedException`, a `ProviderException` as before.

## A post is never sent twice

Nothing in omnipost retries: not the guard, not the wait, not the API clients. A token is refreshed
before a call, never after a refusal; an upload that failed is not resumed nor started again; an
Instagram container is published by one `media_publish`. A post refused (429, 5xx, a lost answer)
is the caller's to send again, once it has checked it did not go out (`status()`): neither network
offers an idempotency key for a post. The HTTP client a site gives is kept that
way too: Symfony's `framework.http_client.retry_failed` retries 423, 425, 429, 502 and 503 for
every method, POST included, so the factory keeps the client it is given (the bundle's
`http_client`) with its retries off (omnisense/contracts' `Http\NoRetry`, which
omnisense/contracts 1568f2c brings). A test client (`MockHttpClient`) is kept as given. With an
older kernel the client is kept as given: a site that turns `retry_failed` on then gives omnipost
a scoped client without it.
