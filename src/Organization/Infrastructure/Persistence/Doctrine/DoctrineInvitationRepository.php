<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Domain\Model\Invitation;
use App\Organization\Domain\Model\InvitationId;
use App\Organization\Domain\Model\InvitationToken;
use App\Organization\Domain\Model\InvitedEmail;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Repository\InvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final readonly class DoctrineInvitationRepository implements InvitationRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Invitation $invitation): void
    {
        $this->entityManager->persist($invitation);
        $this->entityManager->flush();
    }

    public function remove(Invitation $invitation): void
    {
        $this->entityManager->remove($invitation);
        $this->entityManager->flush();
    }

    public function ofToken(InvitationToken $token): ?Invitation
    {
        return $this->entityManager
            ->createQuery('SELECT i FROM '.Invitation::class.' i WHERE i.token = :token')
            ->setParameter('token', $token->toString())
            ->getOneOrNullResult();
    }

    public function ofId(string $id): ?Invitation
    {
        try {
            $identifier = InvitationId::fromString($id)->toString();
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->entityManager
            ->createQuery('SELECT i FROM '.Invitation::class.' i WHERE i.id = :id')
            ->setParameter('id', $identifier)
            ->getOneOrNullResult();
    }

    public function pendingFor(OrganizationId $organizationId, InvitedEmail $email): ?Invitation
    {
        return $this->entityManager
            ->createQuery(
                'SELECT i FROM '.Invitation::class.' i'
                .' WHERE i.organizationId = :organization AND i.email = :email',
            )
            ->setParameter('organization', $organizationId->toString())
            ->setParameter('email', $email->toString())
            ->getOneOrNullResult();
    }

    public function ofOrganization(OrganizationId $organizationId): array
    {
        /** @var list<Invitation> $invitations */
        $invitations = $this->entityManager
            ->createQuery(
                'SELECT i FROM '.Invitation::class.' i'
                .' WHERE i.organizationId = :organization ORDER BY i.createdAt DESC',
            )
            ->setParameter('organization', $organizationId->toString())
            ->getResult();

        return $invitations;
    }
}
