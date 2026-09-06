<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

use App\Organization\Domain\Exception\InvitationCannotBeAccepted;
use App\Shared\Domain\AggregateRoot;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Une place proposée dans une organisation.
 *
 * **Cet agrégat n'est pas `TenantScoped`**, comme `CalendarFeed` et pour la
 * même raison : celui qui accepte n'est pas encore membre, et le filtre — armé
 * sur *son* organisation courante — masquerait l'invitation qu'il vient de
 * recevoir. Le cloisonnement tient ici au jeton et à l'adresse.
 *
 * Le jeton seul ne fait entrer personne : l'adresse du compte qui accepte doit
 * être celle qui a été invitée, faute de quoi un lien transféré ouvrirait la
 * porte à n'importe qui.
 */
final class Invitation extends AggregateRoot
{
    private const int VALID_FOR_DAYS = 7;

    private function __construct(
        private readonly InvitationId $id,
        private readonly OrganizationId $organizationId,
        private readonly InvitedEmail $email,
        private readonly OrganizationRole $role,
        private readonly InvitationToken $token,
        private readonly MemberId $invitedBy,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $acceptedAt,
    ) {
    }

    public static function open(
        InvitationId $id,
        OrganizationId $organizationId,
        InvitedEmail $email,
        OrganizationRole $role,
        InvitationToken $token,
        MemberId $invitedBy,
        DateTimeImmutable $now,
    ): self {
        // Le propriétaire se transmet, il ne se distribue pas : pouvoir en
        // inviter un second viderait de son sens la règle « au moins un
        // propriétaire ».
        if (OrganizationRole::Owner === $role) {
            throw new InvalidArgumentException('On n\'invite pas quelqu\'un comme propriétaire.');
        }

        return new self(
            $id,
            $organizationId,
            $email,
            $role,
            $token,
            $invitedBy,
            $now,
            $now->modify(\sprintf('+%d days', self::VALID_FOR_DAYS)),
            null,
        );
    }

    public function id(): InvitationId
    {
        return $this->id;
    }

    public function organizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function email(): InvitedEmail
    {
        return $this->email;
    }

    public function role(): OrganizationRole
    {
        return $this->role;
    }

    public function token(): InvitationToken
    {
        return $this->token;
    }

    public function invitedBy(): MemberId
    {
        return $this->invitedBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function acceptedAt(): ?DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function isAccepted(): bool
    {
        return null !== $this->acceptedAt;
    }

    public function isPendingAt(DateTimeImmutable $now): bool
    {
        return !$this->isAccepted() && $now < $this->expiresAt;
    }

    public function acceptedBy(InvitedEmail $email, DateTimeImmutable $now): void
    {
        if ($this->isAccepted()) {
            throw InvitationCannotBeAccepted::becauseItWasAlreadyAccepted();
        }

        if (!$this->email->equals($email)) {
            throw InvitationCannotBeAccepted::becauseItWasAddressedToSomeoneElse();
        }

        if ($now >= $this->expiresAt) {
            throw InvitationCannotBeAccepted::becauseItExpired();
        }

        $this->acceptedAt = $now;
    }
}
