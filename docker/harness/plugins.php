<?php

/**
 * Every provider package: its slug (omnipost/<slug>, github.com/glitchr-omnisense/omnipost-<slug>),
 * its tests' namespace and its factory class.
 */
return [
    'instagram' => ['Omnipost\\Instagram\\Tests\\', 'Omnipost\\Instagram\\InstagramProviderFactory'],
    'youtube' => ['Omnipost\\YouTube\\Tests\\', 'Omnipost\\YouTube\\YouTubeProviderFactory'],
];
