<?php

declare(strict_types=1);

namespace App\Tests\Functional\Routine;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Routine\Application\Query\RoutineQuery;
use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Ouvrir une routine, la cadencer, la cocher.
 *
 * Ce qui distingue une routine d'une liste tient en une phrase : cocher ne
 * retire rien, cela vaut jusqu'à la période suivante.
 */
final class RoutineJourneyTest extends WebTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use LogsIn;
    use ResetDatabase;

    public function testARoutineIsOpenedFromTheTasksScreenAndNamedInPlace(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $this->openRoutine($client);

        self::assertResponseIsSuccessful();
        self::assertSame('Nouvelle routine', $crawler->filter('.fx-list__title')->text());
        // Quotidienne par défaut : la cadence la plus courante, et celle qui ne
        // demande aucun réglage.
        self::assertStringContainsString('Quotidienne', $crawler->filter('.fx-pill--selected')->text());
    }

    public function testAnAccountWithoutAnyRoutineCanStillFindThem(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/');

        // La section reste, vide, comme celles des obsessions et des listes :
        // la cacher reviendrait à masquer l'existence même des routines à qui
        // n'en a pas encore.
        self::assertStringContainsString('Routines', $crawler->filter('.fx-sidebar__scroll')->text());
        // Et le menu de création en propose une : sans cela, un compte neuf
        // n'aurait aucun chemin vers les routines depuis la barre latérale.
        self::assertCount(1, $crawler->filter('.fx-sidebar__create form[action="/routines/nouvelle"]'));
    }

    public function testARoutineIsOpenedFromTheSidebarMenu(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/');
        $client->submit($crawler->filter('.fx-sidebar__create form[action="/routines/nouvelle"]')->form());
        $crawler = $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSame('Nouvelle routine', $crawler->filter('.fx-list__title')->text());
    }

    public function testTheRoutineShowsUpInTheSidebarAndOnTheTasksScreen(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->openRoutine($client);
        $this->rename($client, 'Matin');

        $crawler = $client->request('GET', '/taches');

        self::assertStringContainsString('Matin', $crawler->filter('.fx-sidebar__group')->text());
        self::assertStringContainsString('Matin', $crawler->filter('.fx-board')->last()->text());
    }

    public function testTickingHoldsAndUnticksOnASecondClick(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $id = $this->routineIdAfterOpening($client);
        $this->addStep($client, $account, $id, 'Lire vingt minutes');

        $itemId = $this->firstItemId();
        $rendered = $this->component($client, $account, $id)
            ->call('tick', ['itemId' => $itemId])
            ->render()
            ->toString();

        self::assertStringContainsString('aria-pressed="true"', $rendered);

        // Le même geste dans les deux sens : recliquer décoche.
        $rendered = $this->component($client, $account, $id)
            ->call('tick', ['itemId' => $itemId])
            ->render()
            ->toString();

        self::assertStringContainsString('aria-pressed="false"', $rendered);
    }

    public function testADailyRoutineOffersNoCalendarToSet(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $id = $this->routineIdAfterOpening($client);
        $this->addStep($client, $account, $id, 'Lire vingt minutes');

        $rendered = $this->component($client, $account, $id)->render()->toString();

        // Chaque jour : il n'y a rien à régler, et sept pastilles inutiles
        // encombreraient la ligne. Rien à dire non plus en regard de chaque
        // étape — « chaque jour » répété trois fois serait du bruit.
        self::assertStringNotContainsString('fx-routine-line__schedule', $rendered);
        self::assertStringNotContainsString('chaque jour', $rendered);
    }

    public function testAWeeklyRoutineLetsEachStepPickItsDays(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $id = $this->routineIdAfterOpening($client);
        $this->addStep($client, $account, $id, 'Trier la boîte de réception');
        $this->setCadence($client, $id, 'weekly');

        $itemId = $this->firstItemId();
        $rendered = $this->component($client, $account, $id)
            ->call('toggleDay', ['itemId' => $itemId, 'day' => 2])
            ->render()
            ->toString();

        self::assertStringContainsString('mar', $rendered);
        self::assertSame([2], $this->routines()->ofId($id)?->items[0]->days);
    }

    public function testTheLastDayCannotBeTakenAway(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $id = $this->routineIdAfterOpening($client);
        $this->addStep($client, $account, $id, 'Trier la boîte de réception');
        $this->setCadence($client, $id, 'weekly');

        $itemId = $this->firstItemId();
        $this->component($client, $account, $id)->call('toggleDay', ['itemId' => $itemId, 'day' => 2]);
        // Décocher le dernier jour ferait retomber l'étape à « n'importe quel
        // jour de la semaine », ce qui n'est pas ce qu'on demande.
        $this->component($client, $account, $id)->call('toggleDay', ['itemId' => $itemId, 'day' => 2]);

        self::assertSame([2], $this->routines()->ofId($id)?->items[0]->days);
    }

    public function testChangingCadenceForgetsTheCalendarsRatherThanKeepingThemHidden(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $id = $this->routineIdAfterOpening($client);
        $this->addStep($client, $account, $id, 'Trier la boîte de réception');
        $this->setCadence($client, $id, 'weekly');
        $this->component($client, $account, $id)->call('toggleDay', ['itemId' => $this->firstItemId(), 'day' => 2]);

        $this->setCadence($client, $id, 'daily');

        // Des jours réglés pour une routine hebdomadaire ne veulent plus rien
        // dire une fois quotidienne : les garder les ferait réapparaître au
        // retour, sans que personne ne les ait redemandés.
        self::assertSame([], $this->routines()->ofId($id)?->items[0]->days);
    }

    public function testARoutineWithSomethingLeftShowsUpOnTheHomeScreen(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $id = $this->routineIdAfterOpening($client);
        $this->rename($client, 'Matin');
        $this->addStep($client, $account, $id, 'Lire vingt minutes');

        self::assertStringContainsString('Matin', $client->request('GET', '/')->text());

        // Une fois tout coché, la section disparaît : une liste de choses faites
        // n'apprend rien le matin suivant.
        $this->component($client, $account, $id)->call('tick', ['itemId' => $this->firstItemId()]);

        self::assertStringNotContainsString('Routines du jour', $client->request('GET', '/')->text());
    }

    public function testDeletingARoutineTakesItsTicksWithIt(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $id = $this->routineIdAfterOpening($client);
        $this->addStep($client, $account, $id, 'Lire vingt minutes');
        $this->component($client, $account, $id)->call('tick', ['itemId' => $this->firstItemId()]);

        $crawler = $client->request('GET', '/routines/'.$id);
        $client->submit($crawler->filter('form[action="/routines/'.$id.'/supprimer"]')->form());
        $client->followRedirect();

        self::assertCount(0, $this->routines()->all());
        self::assertResponseIsSuccessful();
    }

    private function openRoutine(KernelBrowser $client): Crawler
    {
        $crawler = $client->request('GET', '/taches');
        $client->submit($crawler->filter('form[action="/routines/nouvelle"]')->form());

        return $client->followRedirect();
    }

    private function routineIdAfterOpening(KernelBrowser $client): string
    {
        $this->openRoutine($client);

        return $this->routineId($client);
    }

    private function routineId(KernelBrowser $client): string
    {
        return $this->routines()->all()[0]->id;
    }

    private function firstItemId(): string
    {
        return $this->routines()->all()[0]->items[0]->id;
    }

    private function rename(KernelBrowser $client, string $name): void
    {
        $id = $this->routineId($client);
        $crawler = $client->request('GET', '/routines/'.$id);
        $client->submit($crawler->filter('form[action="/routines/'.$id.'/nom"]')->form(['name' => $name]));
        $client->followRedirect();
    }

    private function setCadence(KernelBrowser $client, string $id, string $cadence): void
    {
        $crawler = $client->request('GET', '/routines/'.$id);
        $token = (string) $crawler->filter('form[action="/routines/'.$id.'/cadence"] input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/routines/'.$id.'/cadence', ['_token' => $token, 'cadence' => $cadence]);
        $client->followRedirect();
    }

    private function addStep(KernelBrowser $client, SecurityUser $account, string $id, string $text): void
    {
        $this->component($client, $account, $id)->set('draft', $text)->call('add');
    }

    private function component(KernelBrowser $client, SecurityUser $account, string $id): TestLiveComponent
    {
        return $this->createLiveComponent('RoutineChecklist', ['routineId' => $id], $client)->actingAs($account);
    }

    private function routines(): RoutineQuery
    {
        $query = self::getContainer()->get(RoutineQuery::class);
        self::assertInstanceOf(RoutineQuery::class, $query);

        return $query;
    }
}
