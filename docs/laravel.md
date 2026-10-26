---
title: Laravel
order: 16
---

# Laravel

omnibridge/laravel, the bridge that hosts omnibase in Laravel, has no Omnipost provider, and there
is no other Laravel package for it. The registry is plain PHP: a service provider of the application
builds it once, from a config file, with a `symfony/http-client` client (both provider packages
require it; Laravel's own HTTP client is Guzzle's, which the providers do not take).

```php
// config/omnipost.php
return [
    'providers' => [
        'instagram' => ['factory' => 'instagram', 'options' => [
            'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
            'app_id' => env('INSTAGRAM_APP_ID'),
            'app_secret' => env('INSTAGRAM_APP_SECRET'),
        ]],
        'youtube' => ['factory' => 'youtube', 'options' => [
            'api_key' => env('YOUTUBE_API_KEY'),
            'channel_id' => env('YOUTUBE_CHANNEL_ID'),
            'client_id' => env('YOUTUBE_CLIENT_ID'),
            'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
            'refresh_token' => env('YOUTUBE_REFRESH_TOKEN'),
        ]],
    ],
];
```

```php
// app/Providers/OmnipostServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Omnipost\Instagram\InstagramProviderFactory;
use Omnipost\Registry;
use Omnipost\Validator;
use Omnipost\YouTube\YouTubeProviderFactory;
use Symfony\Component\HttpClient\HttpClient;

final class OmnipostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registry::class, function (): Registry {
            $http = HttpClient::create();

            return new Registry([
                new InstagramProviderFactory($http),
                new YouTubeProviderFactory($http),
            ], config('omnipost.providers'));
        });
        $this->app->singleton(Validator::class);
    }
}
```

Register the provider (`bootstrap/providers.php` on Laravel 11 and later), then inject the registry
and ask for a provider by name:

```php
use Omnipost\Platform;
use Omnipost\Registry;

final class PublishReel   // a queued job: publishing takes time
{
    public function handle(Registry $registry): void
    {
        $instagram = $registry->get('instagram');
        $publication = $instagram->publish($this->post->for(Platform::INSTAGRAM));
        // store $publication->id, then poll status() from a later job
    }
}
```

Differences from the Symfony bundle: list in the provider every factory the application uses (a
hand-built registry knows only the factories it is given), and there is no injection of one
provider by its argument's name - ask the registry. An `env()` left unset is `null`, which the
factories treat as not given. Configuration caching (`php artisan config:cache`) keeps working: the
options are plain arrays read through `config()`. This page was written from the registry's API; it
was not run in a Laravel application.
