<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Domain;

use App\Organization\Domain\Model\Invitation;
use App\Organization\Domain\Model\InvitationId;
use App\Organization\Domain\Model\InvitationToken;
use App\Organization\Domain\Model\InvitedEmail;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Model\OrganizationRole;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Invitation::class)]
#[CoversClass(InvitedEmail::class)]
final class InvitationTest extends TestCase
{
    private const string TOKEN = 'kZ9v3XpQ7bR2tY8nW4mL6jH1sD5fG0aC';

    public function testAnInvitedAddressIsNormalised(): void
    {
        self::assertSame('nicolas@focusyn.fr', InvitedEmail::fromString('  Nicolas@Focusyn.FR ')->toString());
    }

    public function testAnAddressThatIsNotOneIsRefused(): void
    {
        $this->expectExceptionMessage('« pas-une-adresse » n\'est pas une adresse.');
        InvitedEmail::fromString('pas-une-adresse');
    }

    public function testAnInvitationOpensPendingAndExpiring(): void
    {
        $invitation = $this->invite();

        self::assertFalse($invitation->isAccepted());
        self::assertSame(OrganizationRole::Member, $invitation->role());
        // Sept jours : assez pour être lue, trop court pour traîner dans une
        // boîte aux lettres oubliée.
        self::assertSame('2026-09-13 09:00', $invitation->expiresAt()->format('Y-m-d H:i'));
    }

    public function testAcceptingMarksItOnce(): void
    {
        $invitation = $this->invite();
        $accepted = new DateTimeImmutable('2026-09-07 10:00');

        $invitation->acceptedBy(InvitedEmail::fromString('invite@focusyn.fr'), $accepted);

        self::assertTrue($invitation->isAccepted());
        self::assertSame('2026-09-07 10:00', $invitation->acceptedAt()?->format('Y-m-d H:i'));

        $this->expectExceptionMessage('Cette invitation a déjà été acceptée.');
        $invitation->acceptedBy(InvitedEmail::fromString('invite@focusyn.fr'), $accepted);
    }

    public function testOnlyTheInvitedAddressCanAccept(): void
    {
        $invitation = $this->invite();

        // Sans cette vérification, un lien transféré ferait entrer n'importe
        // qui : le jeton seul ne dit pas à qui il était destiné.
        $this->expectExceptionMessage('Cette invitation ne vous est pas adressée.');
        $invitation->acceptedBy(
            InvitedEmail::fromString('quelqun@ailleurs.fr'),
            new DateTimeImmutable('2026-09-07 10:00'),
        );
    }

    public function testAnExpiredInvitationIsRefused(): void
    {
        $invitation = $this->invite();

        $this->expectExceptionMessage('Cette invitation a expiré.');
        $invitation->acceptedBy(
            InvitedEmail::fromString('invite@focusyn.fr'),
            new DateTimeImmutable('2026-09-14 09:00'),
        );
    }

    public function testAnInvitationKnowsWhetherItIsStillWaiting(): void
    {
        $invitation = $this->invite();

        self::assertTrue($invitation->isPendingAt(new DateTimeImmutable('2026-09-10 09:00')));
        self::assertFalse($invitation->isPendingAt(new DateTimeImmutable('2026-09-20 09:00')));

        $invitation->acceptedBy(InvitedEmail::fromString('invite@focusyn.fr'), new DateTimeImmutable('2026-09-07 10:00'));
        self::assertFalse($invitation->isPendingAt(new DateTimeImmutable('2026-09-10 09:00')));
    }

    public function testNobodyIsEverInvitedAsOwner(): void
    {
        // Le propriétaire se transmet, il ne se distribue pas : deux
        // propriétaires par invitation ferait de la règle « au moins un
        // propriétaire » une règle sans effet.
        $this->expectExceptionMessage('On n\'invite pas quelqu\'un comme propriétaire.');
        Invitation::open(
            InvitationId::generate(),
            OrganizationId::generate(),
            InvitedEmail::fromString('invite@focusyn.fr'),
            OrganizationRole::Owner,
            InvitationToken::fromString(self::TOKEN),
            MemberId::generate(),
            new DateTimeImmutable('2026-09-06 09:00'),
        );
    }

    private function invite(): Invitation
    {
        return Invitation::open(
            InvitationId::generate(),
            OrganizationId::generate(),
            InvitedEmail::fromString('invite@focusyn.fr'),
            OrganizationRole::Member,
            InvitationToken::fromString(self::TOKEN),
            MemberId::generate(),
            new DateTimeImmutable('2026-09-06 09:00'),
        );
    }
}
