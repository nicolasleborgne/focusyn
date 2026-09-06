<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Reminder\Domain\Repository\ReminderRepository;
use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * La revue du soir : ce qu'a été la journée, et ce qu'on en reporte.
 *
 * Le seul écran qui lit trois contextes à la fois. Il n'en connaît aucun —
 * chacun lui parle par son port.
 */
final class EveningReviewTest extends WebTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use LogsIn;
    use ResetDatabase;

    public function testTheReviewCountsTheDayWithoutCountingYesterday(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $list = $this->openList($client);
        $this->addTask($client, $account, $list, 'Écrire la synthèse');
        $this->addTask($client, $account, $list, 'Trier les notes en friche');
        $this->tickFirstTask($client, $account, $list);

        $rendered = $this->review($client, $account)->render()->toString();

        // Une faite, une ouverte : c'est la journée, pas le cumul.
        self::assertStringContainsString('>1<', $rendered);
        self::assertStringContainsString('tâches faites', $rendered);
    }

    public function testWhatRemainsOpenIsOfferedToBeCarriedOver(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $list = $this->openList($client);
        $this->addTask($client, $account, $list, 'Écrire la synthèse');

        $rendered = $this->review($client, $account)->render()->toString();

        self::assertStringContainsString('Écrire la synthèse', $rendered);
        self::assertStringContainsString('Reporter à demain', $rendered);
    }

    public function testCarryingOverPutsOneReminderPerTask(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $list = $this->openList($client);
        $this->addTask($client, $account, $list, 'Écrire la synthèse');
        $this->addTask($client, $account, $list, 'Trier les notes en friche');

        $rendered = $this->review($client, $account)->call('deferAll')->render()->toString();

        self::assertStringContainsString('Reporté à demain', $rendered);

        // Un rappel par tâche, sur son propre sujet : c'est ce qui permet de
        // n'en déplacer qu'une ensuite sans défaire le report entier.
        $reminders = $this->reminders();
        self::assertCount(2, $reminders);
        self::assertSame('09:00', $reminders[0]->dueAt()->format('H:i'));
    }

    public function testWithNothingOpenTheReviewSaysSoRatherThanOfferingToDefer(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);

        $rendered = $this->review($client, $account)->render()->toString();

        self::assertStringContainsString('Rien ne reste ouvert', $rendered);
        self::assertStringNotContainsString('Reporter à demain', $rendered);
    }

    public function testCarryingOverNothingChangesNothing(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);

        $this->review($client, $account)->call('deferAll');

        self::assertCount(0, $this->reminders());
    }

    public function testTheHomeScreenOpensTheReview(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/');

        // Le déclencheur vit en haut, le dialogue à la fin : l'écoute est
        // déléguée au document, comme pour les rappels.
        self::assertCount(1, $crawler->filter('[data-fx-dialog="review"]'));
        self::assertCount(1, $crawler->filter('.fx-review[data-fx-dialog-name="review"] .fx-review__panel'));
    }

    private function review(KernelBrowser $client, SecurityUser $account): TestLiveComponent
    {
        return $this->createLiveComponent('EveningReview', [], $client)->actingAs($account);
    }

    private function openList(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/taches');
        $client->submit($crawler->filter('form[action="/taches/nouvelle"]')->form());
        $client->followRedirect();

        return (string) $client->getRequest()->attributes->get('id');
    }

    private function addTask(KernelBrowser $client, SecurityUser $account, string $list, string $text): void
    {
        $this->createLiveComponent('TaskChecklist', ['listId' => $list], $client)
            ->actingAs($account)
            ->set('draft', $text)
            ->call('add');
    }

    private function tickFirstTask(KernelBrowser $client, SecurityUser $account, string $list): void
    {
        $id = (string) $client->request('GET', '/taches/'.$list)
            ->filter('.fx-task-line button[data-live-task-id-param]')
            ->first()
            ->attr('data-live-task-id-param');

        $this->createLiveComponent('TaskChecklist', ['listId' => $list], $client)
            ->actingAs($account)
            ->call('toggle', ['taskId' => $id]);
    }

    /** @return list<\App\Reminder\Domain\Model\Reminder> */
    private function reminders(): array
    {
        $repository = self::getContainer()->get(ReminderRepository::class);
        self::assertInstanceOf(ReminderRepository::class, $repository);

        return $repository->all();
    }
}
