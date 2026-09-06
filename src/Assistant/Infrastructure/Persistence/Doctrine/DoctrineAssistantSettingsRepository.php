<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Persistence\Doctrine;

use App\Assistant\Domain\Model\AssistantSettings;
use App\Assistant\Domain\Model\OwnerId;
use App\Assistant\Domain\Repository\AssistantSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAssistantSettingsRepository implements AssistantSettingsRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(AssistantSettings $settings): void
    {
        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }

    public function remove(AssistantSettings $settings): void
    {
        $this->entityManager->remove($settings);
        $this->entityManager->flush();
    }

    public function ofOwner(OwnerId $owner): ?AssistantSettings
    {
        return $this->entityManager
            ->createQuery('SELECT s FROM '.AssistantSettings::class.' s WHERE s.ownerId = :owner')
            ->setParameter('owner', $owner->toString())
            ->getOneOrNullResult();
    }
}
