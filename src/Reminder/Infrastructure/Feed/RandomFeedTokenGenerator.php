<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Feed;

use App\Reminder\Application\Port\FeedTokenGenerator;
use App\Reminder\Domain\Model\FeedToken;

/**
 * 32 octets tirés au sort, écrits en base64 sans caractère à échapper dans une
 * adresse. C'est le même ordre de grandeur qu'un jeton de session : le flux
 * n'a pas d'autre protection.
 */
final readonly class RandomFeedTokenGenerator implements FeedTokenGenerator
{
    public function generate(): FeedToken
    {
        return FeedToken::fromString(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }
}
