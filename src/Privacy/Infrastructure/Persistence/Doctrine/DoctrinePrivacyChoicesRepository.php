<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Persistence\Doctrine;

use App\Privacy\Domain\Model\PrivacyChoices;
use App\Privacy\Domain\Model\SubjectId;
use App\Privacy\Domain\Repository\PrivacyChoicesRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePrivacyChoicesRepository implements PrivacyChoicesRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(PrivacyChoices $choices): void
    {
        $this->entityManager->persist($choices);
        $this->entityManager->flush();
    }

    public function ofSubject(SubjectId $subjectId): ?PrivacyChoices
    {
        return $this->entityManager->find(PrivacyChoices::class, $subjectId);
    }
}
