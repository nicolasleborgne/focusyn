<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\OAuthIdentity;
use App\Identity\Domain\Model\OAuthProvider;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Event\DomainEventBus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DomainEventBus $events,
    ) {
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Après le flush, et pas avant : un événement de domaine annonce un
        // fait acquis. Publier plus tôt exposerait les autres contextes à une
        // écriture qui peut encore échouer.
        $this->events->publish(...$user->releaseEvents());
    }

    public function ofId(UserId $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function ofEmail(EmailAddress $email): ?User
    {
        return $this->entityManager
            ->createQuery('SELECT u FROM '.User::class.' u WHERE u.email = :email')
            ->setParameter('email', $email->toString())
            ->getOneOrNullResult();
    }

    public function ofOAuthIdentity(OAuthProvider $provider, string $externalId): ?User
    {
        return $this->entityManager
            ->createQuery(
                'SELECT u FROM '.User::class.' u'
                .' JOIN '.OAuthIdentity::class.' i WITH i.user = u'
                .' WHERE i.provider = :provider AND i.externalId = :externalId',
            )
            ->setParameter('provider', $provider->value)
            ->setParameter('externalId', $externalId)
            ->getOneOrNullResult();
    }

    public function emailIsTaken(EmailAddress $email): bool
    {
        $count = $this->entityManager
            ->createQuery('SELECT COUNT(u.id) FROM '.User::class.' u WHERE u.email = :email')
            ->setParameter('email', $email->toString())
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
