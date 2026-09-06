<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Model\Device;
use App\Identity\Domain\Model\LoginSession;
use App\Identity\Domain\Model\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoginSession::class)]
#[CoversClass(Device::class)]
final class LoginSessionTest extends TestCase
{
    public function testAnAppareilIsNamedFromWhatTheBrowserSaysOfItself(): void
    {
        self::assertSame(
            'Chrome — macOS',
            Device::fromUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36')->toString(),
        );

        self::assertSame(
            'Safari — iOS',
            Device::fromUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1')->toString(),
        );

        self::assertSame(
            'Firefox — Linux',
            Device::fromUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0')->toString(),
        );
    }

    public function testAnUnrecognisableBrowserIsNotGuessedAt(): void
    {
        // Mieux vaut « Appareil inconnu » qu'une identification fausse : cette
        // ligne sert à décider d'une révocation.
        self::assertSame('Appareil inconnu', Device::fromUserAgent('curl/8.5.0')->toString());
        self::assertSame('Appareil inconnu', Device::fromUserAgent('')->toString());
    }

    public function testASessionRemembersWhenItWasSeenLast(): void
    {
        $session = $this->open();

        self::assertSame('2026-09-06 09:00', $session->lastSeenAt()->format('Y-m-d H:i'));

        $session->seenAt(new DateTimeImmutable('2026-09-06 11:30'));

        self::assertSame('2026-09-06 11:30', $session->lastSeenAt()->format('Y-m-d H:i'));
        self::assertSame('2026-09-06 09:00', $session->openedAt()->format('Y-m-d H:i'));
    }

    public function testTimeNeverRunsBackwards(): void
    {
        $session = $this->open();
        $session->seenAt(new DateTimeImmutable('2026-09-06 11:30'));

        // Deux requêtes concurrentes peuvent arriver dans le désordre : la plus
        // ancienne ne doit pas rajeunir la session.
        $session->seenAt(new DateTimeImmutable('2026-09-06 10:00'));

        self::assertSame('2026-09-06 11:30', $session->lastSeenAt()->format('Y-m-d H:i'));
    }

    private function open(): LoginSession
    {
        return LoginSession::open(
            'a1b2c3d4e5f6',
            UserId::generate(),
            Device::fromUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0'),
            new DateTimeImmutable('2026-09-06 09:00'),
        );
    }
}
