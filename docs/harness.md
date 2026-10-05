# The Docker harness

`docker/` runs this package with every `omnipost/*` provider installed - from GitHub (branch 1.x),
or from the checkouts beside this one when `OMNIPOST_PLUGINS=../..` is set in `docker/.env` - and
a console that exercises them with the tokens in `docker/.env`.

```sh
cd docker && cp .env.dist .env       # your accounts' tokens, when you have them
docker compose run --rm omnipost providers
```

| Command | |
|---|---|
| `providers` | the providers installed, and what each can do with the variables in `.env` |
| `capabilities <provider>` | what the network accepts (no token needed) |
| `validate <provider> <media> [caption]` | the violations a post would meet: nothing is sent |
| `account <provider>` | the connected account |
| `feed <provider>` | the account's posts, newest first (`--limit`, `--cursor`) |
| `publish <provider> <media> [caption]` | a post published, then followed until it is, or fails (`--wait`) |
| `status <provider> <id>` | where a publication stands |
| `refresh <provider>` | a fresh token, printed as a site would store it |
| `authorize <provider> <redirect-uri>`, `exchange <provider> <code> <redirect-uri>` | connecting the account |
| `bare` | plain PHP: the registry built by hand, two posts checked, YouTube asked, what PHP loaded |
| `test` | every package's tests |

`validate` and `publish` take `--title`, `--kind`, `--tag`, `--file`, `--cover`, `--duration`,
`--width`, `--height` and `-o option=value`; they measure what they can (the type and size the
server announces, a local `--file`).

```sh
docker compose run --rm omnipost capabilities instagram
docker compose run --rm omnipost validate instagram https://site.example/reel.mp4 "Strauss" --duration 42 --width 1080 --height 1920 --tag violin
docker compose run --rm omnipost feed youtube --limit 5
docker compose run --rm omnipost publish youtube https://site.example/reel.mp4 "Strauss" --title "Strauss, Sonata" -o privacy=unlisted
```

## Bare: no bundle, no container

The console above is a `symfony/console` application over a registry built by hand; `bare` is
less still - one PHP script, `docker/harness/bin/bare`, that requires the autoloader and nothing
else. It builds the `Registry` from the provider packages installed, asks each provider what it
does, checks two posts against what each network accepts (nothing is sent), asks YouTube for the
channel and its two latest videos - the real Data API when `YOUTUBE_API_KEY` and
`YOUTUBE_CHANNEL_ID` are set, or with `--recorded` the answers kept in
`docker/harness/recorded/` - then lists what PHP loaded and exits 1 if a class of a framework is
among it (`Symfony\Component\DependencyInjection`, `Config`, `HttpKernel`, `HttpFoundation`, a
bundle, a bridge, Doctrine, Twig):

```
$ docker compose run --rm omnipost bare --recorded
Omnipost in bare PHP: the registry built by hand, no bundle, no container.

  instagram  publishes, has a feed
  youtube    publishes, has a feed

Checked before anything is sent, a vertical reel of 42 seconds:
  instagram  accepted
  youtube    accepted

Checked before anything is sent, a landscape reel of twelve minutes:
  instagram  media[0].ratio: 16:9 is not accepted (accepted: 9:16, 4:5, 1:1).
  youtube    title: A title is required.

omnipost/youtube, from the answers kept in recorded/:
  Claire (@claireviolin), https://www.youtube.com/@claireviolin - 4200 subscribers, 36 videos
  2026-09-30  reel  Strauss, Sonata, 00:58, 1200 views
              https://www.youtube.com/watch?v=shortA
  2026-09-01  video The whole concert, 1:02:03, 50 views
              https://www.youtube.com/watch?v=longB

Loaded from Symfony: Symfony\Component\HttpClient, Symfony\Contracts\HttpClient, Symfony\Contracts\Service
Classes of a framework (DependencyInjection, Config, HttpKernel, HttpFoundation, a bundle, a bridge, Doctrine, Twig): none
```

The answers in `recorded/` were not taken from YouTube: they are written in the shape the Data
API v3 documents, as in omnipost/youtube's tests - the channel and its videos are made up.
`bare --recorded --json` prints the same whole - every class loaded, every file since the
autoloader - without a call: `Tests/BareTest.php` runs it in a process of its own and checks the
list.

The image is `php:8.4-cli-alpine` with Composer; the harness's packages live in the `harness`
volume of the `omnipost-harness` project.
