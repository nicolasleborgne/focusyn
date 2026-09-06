<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\HashedPassword;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Changer d'adresse de connexion.
 *
 * La nouvelle adresse n'est jamais prise sur parole : elle attend d'être
 * confirmée depuis la boîte aux lettres correspondante. Sans quoi une faute de
 * frappe fermerait le compte à son propriétaire.
 */
#[CoversClass(User::class)]
final class EmailChangeTest extends TestCase
{
    public function testANewAddressWaitsBeforeReplacingTheOld(): void
    {
        $user = $this->user();

        $user->requestEmailChange(EmailAddress::fromString('neuve@focusyn.fr'));

        self::assertSame('ancienne@focusyn.fr', $user->email()->toString());
        self::assertSame('neuve@focusyn.fr', $user->pendingEmail()?->toString());
    }

    public function testConfirmingTheWaitingAddressReplacesTheOne(): void
    {
        $user = $this->user();
        $user->requestEmailChange(EmailAddress::fromString('neuve@focusyn.fr'));

        $user->confirmEmailChange(EmailAddress::fromString('neuve@focusyn.fr'));

        self::assertSame('neuve@focusyn.fr', $user->email()->toString());
        self::assertNull($user->pendingEmail());
    }

    public function testConfirmingAnAddressThatIsNotTheWaitingOneChangesNothing(): void
    {
        $user = $this->user();
        $user->requestEmailChange(EmailAddress::fromString('neuve@focusyn.fr'));

        $this->expectExceptionMessage('Aucun changement d\'adresse n\'attend cette confirmation.');
        $user->confirmEmailChange(EmailAddress::fromString('autre@focusyn.fr'));
    }

    public function testConfirmingWithoutHavingAskedIsRefused(): void
    {
        $this->expectExceptionMessage('Aucun changement d\'adresse n\'attend cette confirmation.');
        $this->user()->confirmEmailChange(EmailAddress::fromString('neuve@focusyn.fr'));
    }

    public function testAskingForTheAddressOneAlreadyHasIsRefused(): void
    {
        $this->expectExceptionMessage('C\'est déjà votre adresse.');
        $this->user()->requestEmailChange(EmailAddress::fromString('ancienne@focusyn.fr'));
    }

    public function testAskingAgainReplacesTheWaitingAddress(): void
    {
        $user = $this->user();
        $user->requestEmailChange(EmailAddress::fromString('neuve@focusyn.fr'));

        // On se ravise : c'est la dernière demande qui vaut, et le lien émis
        // pour la précédente cesse d'ouvrir quoi que ce soit.
        $user->requestEmailChange(EmailAddress::fromString('encore@focusyn.fr'));

        self::assertSame('encore@focusyn.fr', $user->pendingEmail()?->toString());
    }

    public function testTheChangeCanBeCalledOff(): void
    {
        $user = $this->user();
        $user->requestEmailChange(EmailAddress::fromString('neuve@focusyn.fr'));

        $user->cancelEmailChange();

        self::assertNull($user->pendingEmail());
        self::assertSame('ancienne@focusyn.fr', $user->email()->toString());
    }

    private function user(): User
    {
        return User::register(
            UserId::generate(),
            EmailAddress::fromString('ancienne@focusyn.fr'),
            HashedPassword::fromHash('$2y$13$abcdefghijklmnopqrstuv'),
            new DateTimeImmutable('2026-09-06 09:00'),
        );
    }
}
