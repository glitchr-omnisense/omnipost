---
title: Reading an account
order: 33
---

# Reading an account

`FeedInterface` reads one's own account: who it is (`account()`) and what it posted (`feed()`),
newest first, a page at a time - what a site caches to show a wall of its latest posts.

```php
$account = $instagram->account();
printf("%s (%s) %s, %d followers, %d posts, %s\n", $account->username, $account->name, $account->url, $account->followers, $account->posts, $account->kind);

$feed = $instagram->feed(null, 2);
foreach ($feed->items as $item) {
    printf("  %s %-8s %s thumb=%s children=%d likes=%s\n", $item->publishedAt->format('Y-m-d'), $item->kind->value, $item->permalink, $item->thumbnailUrl, count($item->children), var_export($item->likes, true));
}
echo '  next: ', $feed->next, "\n";
$older = $instagram->feed($feed->next, 2);    // the next page
```

Run on 2026-10-26 on a `MockHttpClient` answering as Instagram documents it (a made-up account),
with the mock's log of each request:

```
> GET https://graph.instagram.com/v23.0/me?fields=id,username,name,profile_picture_url,followers_count,media_count,account_type&access_token=***
claireviolin (Claire) https://www.instagram.com/claireviolin/, 4200 followers, 36 posts, MEDIA_CREATOR
> GET https://graph.instagram.com/v23.0/me/media?fields=id,media_type,media_product_type,media_url,thumbnail_url,permalink,caption,timestamp,like_count,comments_count,children{id,media_type,media_url,thumbnail_url}&limit=2&access_token=***
  2026-10-20 reel     https://www.instagram.com/reel/A/ thumb=https://cdn.example/1.jpg children=0 likes=120
  2026-10-10 carousel https://www.instagram.com/p/B/ thumb=https://cdn.example/21.jpg children=2 likes=NULL
  next: QVFI
```

## Instagram

- `account()`: `GET /<user>` (`me` by default) with the id, username, name, picture, followers,
  posts count and account type; `url` is `https://www.instagram.com/<username>/`.
- `feed($cursor, $limit)`: `GET /<user>/media`, `limit` between 1 and 100, `after` the cursor.
  `next` is the `after` cursor only when Instagram says there is a next page (`paging.next`): the
  last page has cursors too, and gives `null`.
- Kinds: `VIDEO` is `REEL` when `media_product_type` is `REELS` (or missing), else `VIDEO`;
  `CAROUSEL_ALBUM` is `CAROUSEL`, with its children as items of their own; anything else is
  `STORY` when `media_product_type` is `STORY`, else `IMAGE`.
- `url` is the media (`media_url`); `thumbnailUrl` is `thumbnail_url`, else for a picture the
  picture itself, for a carousel its first child's; `likes` and `comments` as Instagram gives them;
  `duration` and `views` are null.
- Someone else's public post needs no API: Instagram's own embed
  (`<blockquote class="instagram-media" data-instgrm-permalink="...">` and
  `//www.instagram.com/embed.js`).

## YouTube

- `account()`: the channel, `GET /channels?part=snippet,statistics,contentDetails`, by
  `channel_id` with the API key, or the token's own channel (`mine=true`) when no `channel_id` is
  set. The username is the channel's handle (`customUrl`, `@claireviolin`), else its title; `url`
  `https://www.youtube.com/<handle>` (or `/channel/<id>`); `followers` the subscribers, null when the
  channel hides them; `posts` the videos count. The channel is read once per provider object.
- `feed($cursor, $limit)`: the channel's uploads playlist (`playlistItems`, `maxResults` between 1
  and 50, `pageToken` the cursor), then one `videos?part=contentDetails,statistics` call for the
  page's durations and counts. A video of 180 seconds at most is a `REEL`, a longer one a `VIDEO`
  (the API does not tell a Short, nor its ratio). `permalink` is the watch page, `url` the embed
  player (`https://www.youtube.com/embed/<id>`), `caption` the video's title, the thumbnail the
  largest given, `publishedAt` the video's publication time; likes, comments and views from the
  statistics.
- A key does not see a private video (the provider's own note): read as the channel (no
  `channel_id`, the OAuth options set) to list them.

The harness's `bare --recorded` shows YouTube's account and feed on recorded answers
([harness](harness.md)).

## Caching

The networks limit the calls: read the feed from a scheduled job, store the items (`raw` keeps the
network's own answer), and show the stored wall.
