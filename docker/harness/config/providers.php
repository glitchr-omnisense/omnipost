<?php

/**
 * One provider per network, its options from the environment (.env). Every
 * installed provider is built - capabilities and validate need no token -
 * and "needs" says what it takes to read and to publish: any one of the
 * lists of variables, all of them set.
 *
 * @return array<string, array{factory: string, needs: array{read: list<list<string>>, publish: list<list<string>>}, options: array<string, mixed>}>
 */
$env = static fn (string $key, mixed $default = null): mixed => (false !== ($v = getenv($key)) && '' !== $v) ? $v : $default;

return [
    'instagram' => [
        'factory' => 'instagram',
        'needs' => ['read' => [['INSTAGRAM_ACCESS_TOKEN']], 'publish' => [['INSTAGRAM_ACCESS_TOKEN']]],
        'options' => ['access_token' => $env('INSTAGRAM_ACCESS_TOKEN'), 'app_id' => $env('INSTAGRAM_APP_ID'), 'app_secret' => $env('INSTAGRAM_APP_SECRET'), 'user_id' => $env('INSTAGRAM_USER_ID')],
    ],
    'youtube' => [
        'factory' => 'youtube',
        'needs' => [
            'read' => [['YOUTUBE_API_KEY', 'YOUTUBE_CHANNEL_ID'], ['YOUTUBE_CLIENT_ID', 'YOUTUBE_CLIENT_SECRET', 'YOUTUBE_REFRESH_TOKEN']],
            'publish' => [['YOUTUBE_CLIENT_ID', 'YOUTUBE_CLIENT_SECRET', 'YOUTUBE_REFRESH_TOKEN']],
        ],
        'options' => ['api_key' => $env('YOUTUBE_API_KEY'), 'channel_id' => $env('YOUTUBE_CHANNEL_ID'), 'client_id' => $env('YOUTUBE_CLIENT_ID'), 'client_secret' => $env('YOUTUBE_CLIENT_SECRET'), 'refresh_token' => $env('YOUTUBE_REFRESH_TOKEN'), 'privacy' => $env('YOUTUBE_PRIVACY')],
    ],
];
