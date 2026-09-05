<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notebook;

use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Model\ObsessionName;
use App\Notebook\Domain\Repository\NoteRepository;
use App\Shared\Domain\TenantId;
use App\Shared\Infrastructure\Persistence\Doctrine\Filter\TenantFilter;
use App\Tests\Factory\Notebook\NoteFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Le test le plus important du projet.
 *
 * Toute la promesse du multi-organisations tient à ceci : deux organisations
 * partagent une base, et aucune requête ne doit jamais franchir la frontière.
 * Chaque nouvelle ressource cloisonnée devra être couverte ici.
 */
final class TenantIsolationTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private TenantId $alice;
    private TenantId $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = TenantId::generate();
        $this->bob = TenantId::generate();

        NoteFactory::new()->ownedBy($this->alice)->titled('Le sommeil biphasique')->about(['Sommeil'])->create();
        NoteFactory::new()->ownedBy($this->alice)->titled('Extraction du café')->about(['Café'])->create();
        NoteFactory::new()->ownedBy($this->bob)->titled('Secret industriel')->about(['Café'])->create();
    }

    public function testAListingNeverCrossesTheBoundary(): void
    {
        $this->workingIn($this->alice);

        $titles = array_map(
            static fn ($note): string => $note->title()->toString(),
            $this->notes()->mostRecent(),
        );

        self::assertCount(2, $titles);
        self::assertNotContains('Secret industriel', $titles);
    }

    public function testANoteOfAnotherOrganizationCannotBeFetchedById(): void
    {
        $foreign = NoteFactory::new()->ownedBy($this->bob)->create();
        $this->workingIn($this->alice);

        self::assertNull(
            $this->notes()->ofId($foreign->id()),
            'Connaître l\'identifiant ne doit rien donner : c\'est le cas d\'usage d\'une URL devinée.',
        );
    }

    public function testSearchNeverCrossesTheBoundary(): void
    {
        $this->workingIn($this->alice);

        self::assertSame([], $this->notes()->matching('Secret'));
        self::assertCount(1, $this->notes()->matching('sommeil'));
    }

    public function testFilteringByObsessionNeverCrossesTheBoundary(): void
    {
        $this->workingIn($this->alice);

        $notes = $this->notes()->taggedWith(ObsessionName::fromString('Café'));

        self::assertCount(1, $notes, 'Les deux organisations ont une note « Café » ; on ne doit voir que la sienne.');
        self::assertSame('Extraction du café', $notes[0]->title()->toString());
    }

    public function testCountersNeverCrossTheBoundary(): void
    {
        $this->workingIn($this->alice);
        self::assertSame(2, $this->notes()->count());

        $this->workingIn($this->bob);
        self::assertSame(1, $this->notes()->count());
    }

    public function testObsessionCountsNeverCrossTheBoundary(): void
    {
        $this->workingIn($this->alice);

        $counts = $this->notes()->obsessionCounts();

        self::assertCount(2, $counts);
        self::assertSame(1, array_sum(array_column($counts, 'count')) - 1);
    }

    public function testObsessionCountsComeBackAsPrimitives(): void
    {
        $this->workingIn($this->alice);

        $counts = $this->notes()->obsessionCounts();

        // Le nom est hydraté en objet valeur par le type Doctrine ; le contrat
        // du dépôt annonce des chaînes. Sans cette conversion, la barre
        // latérale explose au premier rendu.
        foreach ($counts as $obsession) {
            self::assertIsString($obsession['name']);
            self::assertIsString($obsession['slug']);
            self::assertIsInt($obsession['count']);
        }

        self::assertSame(['Café', 'Sommeil'], array_column($counts, 'name'));
    }

    public function testWithoutAnyOrganizationNothingIsVisible(): void
    {
        $this->enableFilterWithoutTenant();

        self::assertSame(
            [],
            $this->notes()->mostRecent(),
            'Un filtre armé sans organisation doit tout refuser : échouer ouvert reviendrait à tout montrer.',
        );
        self::assertSame(0, $this->notes()->count());
    }

    public function testAnUnknownIdentifierYieldsNothing(): void
    {
        $this->workingIn($this->alice);

        self::assertNull($this->notes()->ofId(NoteId::generate()));
    }

    private function workingIn(TenantId $tenant): void
    {
        $manager = $this->entityManager();
        $manager->clear();

        $filters = $manager->getFilters();
        $filter = $filters->isEnabled('tenant') ? $filters->getFilter('tenant') : $filters->enable('tenant');
        $filter->setParameter(TenantFilter::PARAMETER, $tenant->toString());
    }

    private function enableFilterWithoutTenant(): void
    {
        $manager = $this->entityManager();
        $manager->clear();

        $filters = $manager->getFilters();

        if ($filters->isEnabled('tenant')) {
            $filters->disable('tenant');
        }

        $filters->enable('tenant');
    }

    private function notes(): NoteRepository
    {
        $repository = self::getContainer()->get(NoteRepository::class);
        self::assertInstanceOf(NoteRepository::class, $repository);

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
