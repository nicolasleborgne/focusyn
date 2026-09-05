<?php

declare(strict_types=1);

namespace App\Tests\Integration\Organization;

use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\MembershipId;
use App\Organization\Domain\Model\OrganizationRole;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Tests\Factory\Organization\OrganizationFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineOrganizationRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testItStoresAnOrganizationWithItsFoundingMembership(): void
    {
        $owner = MemberId::generate();
        $organization = OrganizationFactory::new()->ownedBy($owner)->create();
        $this->clearIdentityMap();

        $restored = $this->repository()->ofId($organization->id());

        self::assertNotNull($restored);
        self::assertCount(1, $restored->memberships());
        self::assertTrue($restored->hasMember($owner));
        self::assertSame(OrganizationRole::Owner, $restored->roleOf($owner));
    }

    public function testMembersAddedToTheAggregateArePersistedWithIt(): void
    {
        $organization = OrganizationFactory::createOne();
        $newcomer = MemberId::generate();

        $organization->addMember(MembershipId::generate(), $newcomer, OrganizationRole::Admin, new DateTimeImmutable());
        $this->repository()->save($organization);
        $this->clearIdentityMap();

        $restored = $this->repository()->ofId($organization->id());

        self::assertNotNull($restored);
        self::assertCount(2, $restored->memberships(), 'La cascade doit écrire les appartenances avec leur agrégat.');
        self::assertSame(OrganizationRole::Admin, $restored->roleOf($newcomer));
    }

    public function testRemovingAMemberDeletesTheRowRatherThanOrphaningIt(): void
    {
        $organization = OrganizationFactory::createOne();
        $newcomer = MemberId::generate();
        $organization->addMember(MembershipId::generate(), $newcomer, OrganizationRole::Member, new DateTimeImmutable());
        $this->repository()->save($organization);

        $organization->removeMember($newcomer);
        $this->repository()->save($organization);
        $this->clearIdentityMap();

        $restored = $this->repository()->ofId($organization->id());

        self::assertNotNull($restored);
        self::assertCount(1, $restored->memberships());
        self::assertSame(
            1,
            $this->countRows('organization_memberships'),
            'orphan-removal doit supprimer la ligne, pas seulement détacher l\'objet.',
        );
    }

    public function testItListsOnlyTheOrganisationsSomebodyBelongsTo(): void
    {
        $member = MemberId::generate();
        $mine = OrganizationFactory::new()->ownedBy($member)->create();
        $alsoMine = OrganizationFactory::createOne();
        $alsoMine->addMember(MembershipId::generate(), $member, OrganizationRole::Member, new DateTimeImmutable());
        $this->repository()->save($alsoMine);
        OrganizationFactory::createOne(); // celle d'un tiers
        $this->clearIdentityMap();

        $found = $this->repository()->ofMember($member);

        $ids = array_map(static fn ($organization): string => $organization->id()->toString(), $found);
        sort($ids);
        $expected = [$mine->id()->toString(), $alsoMine->id()->toString()];
        sort($expected);

        self::assertSame($expected, $ids);
    }

    public function testTheSlugIsUniqueAcrossOrganisations(): void
    {
        OrganizationFactory::new()->named('Atelier', 'atelier')->create();

        self::assertTrue($this->repository()->slugIsTaken('atelier'));
        self::assertFalse($this->repository()->slugIsTaken('atelier-2'));
    }

    public function testAPersonalOrganizationStaysMarkedAsSuch(): void
    {
        $organization = OrganizationFactory::new()->personal()->create();
        $this->clearIdentityMap();

        $restored = $this->repository()->ofId($organization->id());

        self::assertNotNull($restored);
        self::assertTrue($restored->isPersonal());
    }

    private function repository(): OrganizationRepository
    {
        $repository = self::getContainer()->get(OrganizationRepository::class);
        self::assertInstanceOf(OrganizationRepository::class, $repository);

        return $repository;
    }

    private function countRows(string $table): int
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return (int) $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table);
    }

    private function clearIdentityMap(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();
    }
}
