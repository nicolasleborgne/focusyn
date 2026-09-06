<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notebook;

use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Domain\TenantId;
use App\Tests\Factory\Notebook\NoteFactory;
use App\Tests\Functional\LogsIn;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Ce qui relie les notes entre elles, et ce qu'on a cessé de nourrir.
 *
 * Deux suggestions, jamais des verdicts : elles doivent se taire quand elles
 * n'ont rien de solide à dire.
 */
final class RelatedAndDormantTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    public function testANoteAloneRecoupesNothing(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $id = $this->writeNote($client, 'Le sommeil biphasique', 'La nuit se coupait en deux.');

        $crawler = $client->request('GET', '/notes/'.$id);

        self::assertCount(0, $crawler->filter('.fx-related'));
    }

    public function testTwoNotesSharingEnoughWordsAreBroughtTogether(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        // Quatre mots rares en commun : c'est le seuil. Trois n'y suffiraient
        // pas, et c'est justement ce que vérifie le test suivant.
        $shared = 'artificielle nocturne segmentation veille';
        $this->seed('Carnet de douze semaines', $shared);
        $id = $this->writeNote($client, 'Le sommeil biphasique', $shared);

        $crawler = $client->request('GET', '/notes/'.$id);

        self::assertStringContainsString('Carnet de douze semaines', $crawler->filter('.fx-related')->text());
        // Le rapprochement dit sur quoi il repose : sans cela, il faudrait
        // rouvrir la note pour comprendre pourquoi elle est là.
        self::assertStringContainsString('segmentation', $crawler->filter('.fx-related__why')->text());
    }

    public function testASharedObsessionIsEnoughOnItsOwn(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->seed('Extraction du café', 'Rien de commun ici.', ['Café']);
        $id = $this->writeNote($client, 'Mouture et temps', 'Trois essais.');
        $this->tag($client, $id, 'Café');

        $crawler = $client->request('GET', '/notes/'.$id);

        // Une obsession a été posée à la main : c'est le signal le plus sûr
        // dont on dispose, et il vaut à lui seul le seuil.
        self::assertStringContainsString('Extraction du café', $crawler->filter('.fx-related')->text());
        self::assertStringContainsString('Café', $crawler->filter('.fx-related__why')->text());
    }

    public function testOneOrTwoSharedWordsAreNotEnough(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->seed('Grille suisse', 'La marge structure la page.');
        $id = $this->writeNote($client, 'Palais de mémoire', 'La marge du protocole.');

        // Deux mots communs, c'est du bruit : la section se tait.
        self::assertCount(0, $client->request('GET', '/notes/'.$id)->filter('.fx-related'));
    }

    public function testAnObsessionUntouchedForWeeksIsFlaggedOnTheHomeScreen(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->seed('Grille suisse', 'La marge est un argument.', ['Typographie'], '-8 weeks');
        $this->seed('Extraction', 'Le ratio ne décide de rien.', ['Café']);

        $crawler = $client->request('GET', '/');
        $dormant = $crawler->filter('a.fx-prompt[href^="/obsessions/"]');

        // Une ligne, les noms, et de quoi y retourner : c'est un rappel, pas
        // un reproche, et le détail se lit sur l'écran de l'obsession.
        self::assertStringContainsString('Typographie', $dormant->text());
        self::assertStringContainsString('relancer', $dormant->text());
        // Celle qu'on vient d'alimenter n'a rien à faire là.
        self::assertStringNotContainsString('Café', $dormant->text());
    }

    public function testANotebookKeptUpToDateShowsNoDormantSection(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->seed('Extraction', 'Le ratio ne décide de rien.', ['Café']);

        self::assertCount(0, $client->request('GET', '/')->filter('a.fx-prompt[href^="/obsessions/"]'));
    }

    /** @param list<string> $obsessions */
    private function seed(string $title, string $body, array $obsessions = [], string $age = 'now'): void
    {
        // La fabrique tire une date d'écriture au hasard, et c'est elle qui
        // fixe la dernière mention : on la pose, puisque c'est exactement ce
        // que « dormante » regarde.
        NoteFactory::new()
            ->ownedBy($this->tenant())
            ->titled($title)
            ->withBody($body)
            ->about($obsessions)
            ->create(['writtenAt' => new DateTimeImmutable($age)]);
    }

    private function tag(KernelBrowser $client, string $id, string $obsession): void
    {
        $crawler = $client->request('GET', '/notes/'.$id);
        $token = (string) $crawler->filter('form[action="/notes/'.$id.'/obsessions"] input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/notes/'.$id.'/obsessions', ['_token' => $token, 'obsessions' => $obsession]);
        $client->followRedirect();
    }

    /** L'espace personnel du compte connecté, créé à l'inscription. */
    private function tenant(): TenantId
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->ofEmail(EmailAddress::fromString('nicolas@focusyn.fr'));
        self::assertNotNull($user);

        $organizations = self::getContainer()->get(OrganizationRepository::class);
        self::assertInstanceOf(OrganizationRepository::class, $organizations);
        $owned = $organizations->ofMember(MemberId::fromString($user->id()->toString()));
        self::assertNotEmpty($owned);

        return TenantId::fromString($owned[0]->id()->toString());
    }
}
