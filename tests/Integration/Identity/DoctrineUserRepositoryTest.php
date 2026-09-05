<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\HashedPassword;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use App\Tests\Factory\Identity\UserFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Vérifie que l'agrégat traverse la base sans rien perdre : ce sont les objets
 * valeur, mappés par des types Doctrine sur mesure, qui risquent de se
 * dégrader en chaînes au passage.
 */
final class DoctrineUserRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testItStoresAndRestoresAUserWithItsValueObjects(): void
    {
        $repository = $this->repository();
        $id = UserId::generate();

        $repository->save(User::register(
            $id,
            EmailAddress::fromString('nicolas@focusyn.fr'),
            HashedPassword::fromHash('$argon2id$v=19$empreinte'),
            new DateTimeImmutable('2026-09-05 10:00:00'),
        ));
        $this->clearIdentityMap();

        $restored = $repository->ofId($id);

        self::assertNotNull($restored);
        self::assertSame('nicolas@focusyn.fr', $restored->email()->toString());
        self::assertSame('$argon2id$v=19$empreinte', $restored->password()->toString());
        self::assertSame('2026-09-05 10:00:00', $restored->registeredAt()->format('Y-m-d H:i:s'));
    }

    public function testTheIdentifierSurvivesAsATypedObject(): void
    {
        $user = UserFactory::createOne();
        $this->clearIdentityMap();

        $restored = $this->repository()->ofId($user->id());

        self::assertNotNull($restored);
        self::assertTrue(
            $user->id()->equals($restored->id()),
            'L\'identifiant doit revenir typé et égal, pas sous forme de chaîne.',
        );
    }

    public function testItFindsAUserByEmailWhateverTheCaseTyped(): void
    {
        UserFactory::new()->withEmail('nicolas@focusyn.fr')->create();
        $this->clearIdentityMap();

        $found = $this->repository()->ofEmail(EmailAddress::fromString('NICOLAS@Focusyn.FR'));

        self::assertNotNull($found, 'La normalisation de l\'adresse doit rendre la recherche insensible à la casse.');
    }

    public function testItReportsAnUnknownEmailAsFree(): void
    {
        UserFactory::new()->withEmail('nicolas@focusyn.fr')->create();

        self::assertTrue($this->repository()->emailIsTaken(EmailAddress::fromString('nicolas@focusyn.fr')));
        self::assertFalse($this->repository()->emailIsTaken(EmailAddress::fromString('personne@focusyn.fr')));
    }

    public function testAnUnknownIdentifierYieldsNothing(): void
    {
        self::assertNull($this->repository()->ofId(UserId::generate()));
    }

    private function repository(): UserRepository
    {
        $repository = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);

        return $repository;
    }

    private function clearIdentityMap(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();
    }
}
