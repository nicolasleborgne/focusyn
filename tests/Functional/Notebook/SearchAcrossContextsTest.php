<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notebook;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Routine\Application\Query\RoutineQuery;
use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * La recherche traverse les contextes.
 *
 * L'écran annonce ce qu'il fouille : ce qui y est annoncé doit s'y trouver, et
 * ce qui s'y trouve doit y être annoncé.
 */
final class SearchAcrossContextsTest extends WebTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use LogsIn;
    use ResetDatabase;

    public function testARoutineIsFoundByItsName(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $this->routineNamed($client, $account, 'Protocole du matin');

        $rendered = $this->search($client, $account, 'protocole');

        self::assertStringContainsString('Protocole du matin', $rendered);
        // Une fois seulement : quand c'est le nom qui répond, le répéter à
        // droite de lui-même n'apprend rien et se lit comme un bug.
        self::assertSame(1, substr_count($rendered, 'Protocole du matin'));
    }

    public function testARoutineIsFoundByOneOfItsSteps(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $id = $this->routineNamed($client, $account, 'Matin');
        $this->addStep($client, $account, $id, 'Rafraîchir le levain');

        $rendered = $this->search($client, $account, 'levain');

        // C'est l'étape qui a répondu : la ligne doit la montrer, et non
        // seulement le nom de la routine, sinon on ne sait pas pourquoi elle
        // est là.
        self::assertStringContainsString('Rafraîchir le levain', $rendered);
        self::assertStringContainsString('Matin', $rendered);
    }

    public function testTheScreenAnnouncesWhatItActuallySearches(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/recherche');

        // Annoncer « notes · tâches » en fouillant aussi les routines serait
        // une promesse en dessous de ce qui est fait.
        self::assertStringContainsString('routines', $crawler->filter('.fx-search__scope')->text());
    }

    public function testARoutineThatMatchesNothingStaysOut(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $this->routineNamed($client, $account, 'Matin');

        $rendered = $this->search($client, $account, 'fermentation');

        self::assertStringNotContainsString('Matin', $rendered);
    }

    public function testAnEmptyQueryListsNoRoutine(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $this->routineNamed($client, $account, 'Matin');

        // Comme les tâches : au repos, l'écran déroule le carnet, pas le reste.
        $rendered = $this->search($client, $account, '');

        self::assertStringNotContainsString('Matin', $rendered);
    }

    private function search(KernelBrowser $client, SecurityUser $account, string $query): string
    {
        return $this->createLiveComponent('NoteSearch', [], $client)
            ->actingAs($account)
            ->set('query', $query)
            ->render()
            ->toString();
    }

    private function routineNamed(KernelBrowser $client, SecurityUser $account, string $name): string
    {
        $crawler = $client->request('GET', '/taches');
        $client->submit($crawler->filter('form[action="/routines/nouvelle"]')->form());
        $client->followRedirect();

        $id = $this->routines()->all()[0]->id;

        $crawler = $client->request('GET', '/routines/'.$id);
        $client->submit($crawler->filter('form[action="/routines/'.$id.'/nom"]')->form(['name' => $name]));
        $client->followRedirect();

        return $id;
    }

    private function addStep(KernelBrowser $client, SecurityUser $account, string $id, string $text): void
    {
        $this->createLiveComponent('RoutineChecklist', ['routineId' => $id], $client)
            ->actingAs($account)
            ->set('draft', $text)
            ->call('add');
    }

    private function routines(): RoutineQuery
    {
        $query = self::getContainer()->get(RoutineQuery::class);
        self::assertInstanceOf(RoutineQuery::class, $query);

        return $query;
    }
}
