---
title: Publishing
order: 31
---

# Publishing

## One post, every network

```php
<?php // publish.php

require __DIR__.'/vendor/autoload.php';

use Omnipost\Exception\InvalidPostException;
use Omnipost\Instagram\InstagramProviderFactory;
use Omnipost\Model\Media;
use Omnipost\Model\MediaKind;
use Omnipost\Model\Post;
use Omnipost\Model\Variant;
use Omnipost\Platform;
use Omnipost\YouTube\YouTubeProviderFactory;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create();

$video = new Media(MediaKind::VIDEO, 'https://site.example/media/reel.mp4', __DIR__.'/reel.mp4', 'video/mp4', 1080, 1920, 42.0, null, 'https://site.example/media/reel.jpg');
$post = Post::reel($video, 'Strauss, tonight at the Philharmonie.', null, ['violin', 'strauss'])
    ->withVariant(new Variant(Platform::YOUTUBE, title: 'Strauss, Sonata in E-flat - live', options: ['privacy' => 'unlisted']));

$instagram = (new InstagramProviderFactory($http))->create(['access_token' => getenv('INSTAGRAM_ACCESS_TOKEN')]);
$publication = $instagram->publish($post->for(Platform::INSTAGRAM));
echo $publication->state->value, ' ', $publication->id, "\n";
while (!$publication->state->isFinal()) {
    sleep(5);
    $publication = $instagram->status($publication->id);
    echo $publication->state->value, ' ', $publication->id, ' ', $publication->permalink, "\n";
}

$youtube = (new YouTubeProviderFactory($http))->create([
    'client_id' => getenv('YOUTUBE_CLIENT_ID'),
    'client_secret' => getenv('YOUTUBE_CLIENT_SECRET'),
    'refresh_token' => getenv('YOUTUBE_REFRESH_TOKEN'),
]);
$publication = $youtube->publish($post->for(Platform::YOUTUBE));
echo $publication->state->value, ' ', $publication->id, ' ', $publication->permalink, "\n";

try {
    $youtube->publish($post);   // the canonical post: no title
} catch (InvalidPostException $e) {
    echo $e->getMessage(), "\n";
}
```

Run on 2026-10-26 on a `MockHttpClient` answering as Instagram and YouTube document it (the ids are
made up; no network was called) and test credentials in place of the environment's, with the mock's log of each request (`>`, tokens masked):

```
> POST https://graph.instagram.com/v23.0/me/media  media_type=REELS&video_url=https://site.example/media/reel.mp4&caption=Strauss, tonight at the Philharmonie.

#violin #strauss&cover_url=https://site.example/media/reel.jpg&share_to_feed=true&access_token=***
pending 17900000000000001
> GET https://graph.instagram.com/v23.0/17900000000000001?fields=status_code,status&access_token=***
processing 17900000000000001 
> GET https://graph.instagram.com/v23.0/17900000000000001?fields=status_code,status&access_token=***
> POST https://graph.instagram.com/v23.0/me/media_publish  creation_id=17900000000000001&access_token=***
> GET https://graph.instagram.com/v23.0/18000000000000002?fields=id,permalink,timestamp,media_type&access_token=***
published 18000000000000002 https://www.instagram.com/reel/ABC/
> POST https://oauth2.googleapis.com/token  client_id=cid.apps.googleusercontent.com&client_secret=secret&grant_type=refresh_token&refresh_token=1//refresh
> POST https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status  {"snippet":{"title":"Strauss, Sonata in E-flat - live","description":"Strauss, tonight at the Philharmonie.\n\n#violin #strauss","tags":["violin","strauss"]},"status":{"privacyStatus":"unlisted","selfDeclaredMadeForKids":false}}
> PUT https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&upload_id=xyz
processing dQw4w9WgXcQ https://www.youtube.com/watch?v=dQw4w9WgXcQ
[youtube] title: A title is required.
```

What it shows:

- `$post->for($platform)` is what each network gets: YouTube's variant brings the title and the
  `privacy` option; Instagram gets the canonical caption.
- Both networks get the caption with the hashtags appended (`Post::text()`); YouTube also gets the
  tags as tags, without `#`.
- Instagram's id changes from the container's (`17900000000000001`) to the media's
  (`18000000000000002`) when it is published.
- YouTube draws an access token from the refresh token once, opens a resumable upload with the
  metadata, then sends the file.
- The canonical post, without the variant, has no title: YouTube refuses it before any request.

## Instagram, in three steps

1. `publish()` validates the post, then creates a **container**: `POST /<user>/media` with the
   media's public URL - Instagram fetches it. Per kind:

   | Kind | Container fields |
   |---|---|
   | `REEL` | `media_type=REELS`, `video_url`, `caption`, `cover_url` (the post's cover, else the video's), `share_to_feed` (the post's option, else the provider's), `thumb_offset` (the post's option) |
   | `IMAGE` | `image_url`, `caption`, `alt_text` (the media's `alt`) |
   | `STORY` | `media_type=STORIES`, `video_url` or `image_url` - no caption |
   | `CAROUSEL` | one container per item (`is_carousel_item=true`, `media_type=VIDEO` + `video_url` for a video, `image_url` for a picture), then `media_type=CAROUSEL`, `children` (their ids, comma-separated), `caption` |
   | `VIDEO` | refused by the validator (not in Instagram's kinds) |

   Empty fields are left out. It answers `PENDING` with the container's id.
2. `status($containerId)` reads `status_code`: `FINISHED` publishes it (step 3); `ERROR` and
   `EXPIRED` are `FAILED` with Instagram's `status` as the error; anything else (`IN_PROGRESS`) is
   `PROCESSING`.
3. On `FINISHED`, `POST /<user>/media_publish` with `creation_id`, then the media is read: the
   answer is `PUBLISHED` with **the media's id**, its permalink and its time. A container already
   published (`PUBLISHED`) answers `PUBLISHED` with the container's id and no permalink.

`status()` also takes a media's id: Instagram answers error code 100 for a container's fields on a
media, and the media is read instead (`PUBLISHED`, its permalink).

> [!CAUTION]
> Step 3 runs inside `status()`. Two workers polling the same container at once would publish it twice: hold a lock (`symfony/lock`) around `status()` for one publication.

`delete()` throws `NotSupportedException`: Instagram's API cannot delete a post. Instagram also caps
the publications made through the API per account and per 24 hours; the provider does not read the
cap (`GET /<user>/content_publishing_limit`).

## YouTube, one upload

`publish()` validates the post, requires the channel's consent (an access token, or the client id,
secret and refresh token; otherwise `InvalidConfigException` before the file is touched), then:

1. the file: `Media::$path` when set (its size from the file), else **`Media::$url` downloaded to a
   temporary file** first (any status but 200 is a `ProviderException`); the content type is the
   media's `mime`, else the download's, and `video/*` when it is not a video type;
2. `POST <upload_uri>/videos?uploadType=resumable&part=snippet,status` with the metadata:

   | Field | Value |
   |---|---|
   | `snippet.title` | the post's title |
   | `snippet.description` | `Post::text()`: the caption and the hashtags |
   | `snippet.tags` | the tags without `#` (left out when none) |
   | `snippet.categoryId` | the post's `category_id` option, else the provider's (left out when none) |
   | `status.privacyStatus` | `private` when scheduled; else the post's `privacy` option, else the provider's (`private` by default) |
   | `status.selfDeclaredMadeForKids` | `false` |
   | `status.publishAt` | the post's `scheduledAt` in UTC, when it is in the future |

3. `PUT` of the bytes to the URL YouTube answered, with a 300-second idle timeout and no overall
   limit.

The answer is the video: `uploadStatus` `processed` is `PUBLISHED` (or `PENDING` when private with
a `publishAt` still to come), `failed`, `rejected` and `deleted` are `FAILED` with
`failureReason` / `rejectionReason`, anything else (`uploaded`) `PROCESSING`. The permalink
(`https://www.youtube.com/watch?v=<id>`) is set from the start.

A `REEL` is uploaded as any video is: YouTube decides whether it is a Short, from the file (vertical,
at most three minutes). `PUBLISHED` means processed, not public: a `private` video is `PUBLISHED`
too. The videos of an API project that has not passed Google's audit are locked private whatever
`privacy` says; the channel's owner makes them public in YouTube Studio.

`status($videoId)` reads `videos?part=status,snippet` as the channel when it can (a key does not see
a private video), and a video not found is a `ProviderException` (404, `videoNotFound`).
`delete($videoId)` deletes the video as the channel.

## Following a publication

Publishing takes from seconds to minutes. In an application, publish from a message handler or a
command, store the publication's id and state, and poll `status()` later until `isFinal()`, keeping
the id each answer gives:

```php
use Omnipost\Model\Publication;
use Omnipost\Registry;
use Symfony\Component\Lock\LockFactory;

final class FollowPublication
{
    public function __construct(private readonly Registry $registry, private readonly LockFactory $locks)
    {
    }

    public function __invoke(string $provider, string $id): Publication
    {
        $lock = $this->locks->createLock('omnipost.'.$provider.'.'.$id);
        $lock->acquire(true);
        try {
            return $this->registry->get($provider)->status($id);   // store ->id and ->state
        } finally {
            $lock->release();
        }
    }
}
```

## Scheduling

Only YouTube takes a publication time (`Capabilities::$scheduling`): `withScheduledAt($at)` uploads
the video private with `publishAt`, and YouTube publishes it then (`PENDING` until that time).
Instagram does not: the validator refuses a scheduled post for it (`scheduledAt: Scheduling is not
offered: the site must post it at that time itself.`), and the site publishes at that time itself.
A `scheduledAt` already past is ignored by YouTube's provider: the video goes out at once.
