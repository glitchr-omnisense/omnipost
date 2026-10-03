<?php

namespace Omnipost\Model;

/**
 * What a post is. REEL is the one every network takes - a vertical short
 * video (Instagram Reel, YouTube Short, Facebook Reel, TikTok) - and the
 * one a site renders from its template; the others are what a network
 * also offers.
 */
enum PostKind: string
{
    case REEL = 'reel';
    case IMAGE = 'image';
    case CAROUSEL = 'carousel';
    case VIDEO = 'video';
    case STORY = 'story';
}
