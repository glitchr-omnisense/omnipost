---
title: Validating a post
order: 32
---

# Validating a post

Every network refuses some posts: a landscape reel on Instagram, a Short of four minutes, a caption
too long. `Omnipost\Validator` lists what a network would refuse, by field, before anything is
sent - for a back office to show next to the field. `publish()` runs the same check and throws
`InvalidPostException` (with the list) instead of sending.

```php
<?php // validate.php

require __DIR__.'/vendor/autoload.php';

use Omnipost\Instagram\InstagramProviderFactory;
use Omnipost\Model\Media;
use Omnipost\Model\MediaKind;
use Omnipost\Model\Post;
use Omnipost\Model\PostKind;
use Omnipost\Validator;
use Omnipost\YouTube\YouTubeProviderFactory;

$instagram = (new InstagramProviderFactory())->create();
$youtube = (new YouTubeProviderFactory())->create();

$landscape = new Media(MediaKind::VIDEO, 'https://site.example/clip.webm', null, 'video/webm', 1920, 1080, 2.5, 2 << 30);
$post = Post::reel($landscape, str_repeat('la ', 800), null, array_fill(0, 31, 'tag'));

foreach ($instagram->validate($post) as $violation) {
    echo 'instagram ', $violation, "\n";
}
foreach ($youtube->validate($post) as $violation) {
    echo 'youtube   ', $violation, "\n";
}
$square = Post::reel(Media::video('https://site.example/square.mp4', null, 30.0, 1080, 1080), 'Square');
foreach ($instagram->validate($square) as $violation) {
    echo 'instagram ', $violation, "\n";
}
$long = Post::reel(Media::video('https://site.example/long.mp4', null, 240.0, 1080, 1920), 'Long', 'A long one');
foreach ($youtube->validate($long) as $violation) {
    echo 'youtube   ', $violation, "\n";
}
$scheduled = Post::image(Media::image('https://site.example/a.jpg', null, 1080, 1350))->withScheduledAt(new DateTimeImmutable('+1 day'));
foreach ((new Validator())->validate($scheduled, $instagram->capabilities()) as $violation) {
    echo 'instagram ', $violation, "\n";
}
echo json_encode(array_map(fn ($k) => $k->value, $instagram->capabilities()->kinds)), ' ', json_encode(array_map(fn ($k) => $k->value, $youtube->capabilities()->kinds)), "\n";
```

```
$ php validate.php
instagram media[0].duration: 2.5 s, at least 3 s.
instagram media[0].size: 2 GB, at most 1 GB.
instagram media[0].format: video/webm is not accepted (accepted: video/mp4, video/quicktime, mp4, mov).
instagram media[0].ratio: 16:9 is not accepted (accepted: 9:16, 4:5, 1:1).
instagram caption: 2556 characters with the hashtags, at most 2200.
instagram tags: 31 hashtags, at most 30.
youtube   tags: 31 hashtags, at most 30.
youtube   title: A title is required.
instagram media[0].ratio: 1:1: a reel is 9:16.
youtube   media[0].duration: 240.0 s, at most 180 s for a Short.
instagram scheduledAt: Scheduling is not offered: the site must post it at that time itself.
["reel","image","carousel","story"] ["reel","video"]
```

(run on 2026-10-26, PHP 8.4, no network: nothing is called). No credential is needed: the
providers are built with no option.

## Two levels

- `Validator::validate(Post $post, Capabilities $c): list<Violation>` checks what a
  `Capabilities` can say, the same for every network.
- `$provider->validate(Post $post)` (Instagram and YouTube) adds what depends on the post's kind,
  and is what `publish()` runs. Call it rather than the validator when the provider is at hand.

Validate `$post->for($platform)`, the post that network gets, not the canonical post.

## What the validator checks

In this order, each failure a `Violation` (`path: message`):

| Path | Rule |
|---|---|
| `kind` | the kind is one of `kinds` |
| `media` | at least one media; at most `mediaMaxCount` |
| `media[i].duration` | a video's duration within `minDuration` / `maxDuration` |
| `media[i].size` | a video's size under `maxVideoSize`, a picture's under `maxImageSize` |
| `media[i].format` | the `mime`, or the URL's extension, among `videoFormats` / `imageFormats` |
| `media[i].ratio` | `width / height` within `ratioTolerance` of one of `aspectRatios` |
| `media[i].width`, `media[i].height` | at least `minWidth` / `minHeight` |
| `caption` | `Post::text()` - the caption with its hashtags - at most `captionMaxLength` characters (`mb_strlen`) |
| `tags` | at most `maxTags` hashtags |
| `title` | present when `titleRequired`; at most `titleMaxLength` characters |
| `scheduledAt` | only where `scheduling` is offered |

A rule is checked only when both the bound and the media's value are known: a duration, size,
format (`mime` null), ratio or width the media does not carry is not checked. `Media::image()` and
`Media::video()` leave `mime` and `size` null - build with `new Media(...)` to have them checked.
The format check accepts the extension of the URL's path when the mime type is not listed: a
`video/x-unknown` at `.../clip.mp4` passes for Instagram.

## What each network accepts

From each provider's `capabilities()`:

| | Instagram | YouTube |
|---|---|---|
| Kinds | reel, image, carousel, story | reel, video |
| Media | up to 10 (a carousel's) | 1 |
| Video duration | 3 s to 900 s | - (a Short: 180 s, below) |
| Ratios | 9:16, 4:5, 1:1, within 1 % | any |
| Video size | 1 GiB | - |
| Image size | 8 MiB | - |
| Video formats | `video/mp4`, `video/quicktime`, `mp4`, `mov` | `video/mp4`, `video/quicktime`, `video/webm`, `mp4`, `mov`, `webm` |
| Image formats | `image/jpeg`, `jpg`, `jpeg` | - |
| Minimum width | 320 px | - |
| Caption | 2200 characters | 5000 (the description) |
| Hashtags | 30 | 30 |
| Title | - | required, 100 characters |
| Scheduling | no | yes |

What the providers' `validate()` adds:

- **Instagram**: a carousel has at least 2 media; any other kind exactly one; a reel is a video and
  is 9:16 (the validator lets 4:5 and 1:1 through for pictures); a picture post is a picture.
  Instagram's capabilities are one set for every kind, so the 3 s - 15 min and the size bounds apply
  to a carousel's or a story's video too, and a story is not held to 9:16.
- **YouTube**: the media is a video; a `REEL` (a Short) lasts at most 180 seconds
  (`YouTubeProvider::SHORT_MAX_DURATION`). A longer vertical video is posted as a `VIDEO`.

## Showing the violations

```php
use Omnipost\Exception\InvalidPostException;
use Symfony\Component\Form\FormError;

try {
    $instagram->publish($post->for(Platform::INSTAGRAM));
} catch (InvalidPostException $e) {
    foreach ($e->violations as $violation) {
        $form->get(str_starts_with($violation->path, 'media') ? 'media' : $violation->path)->addError(new FormError($violation->message));
    }
}
```

`InvalidPostException::$provider` names the provider; its message is every violation, joined by
`; ` (`[youtube] title: A title is required.`).
