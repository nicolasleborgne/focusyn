<?php

declare(strict_types=1);

namespace App\Tests\Functional\Reminder;

use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Poser une échéance sur une note, la déplacer, l'emporter dans un agenda,
 * la retirer.
 */
final class ReminderJourneyTest extends WebTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use LogsIn;
    use ResetDatabase;

    public function testANoteWithoutAReminderOffersToSetOne(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet');

        $chip = $client->request('GET', '/notes/'.$noteId)->filter('.fx-reminder-chip');

        self::assertCount(1, $chip);
        self::assertSame('Rappel', trim($chip->text()));
        self::assertStringNotContainsString('fx-reminder-chip--set', (string) $chip->attr('class'));
        self::assertSame('note:'.$noteId, $chip->attr('data-fx-remind-subject'));
    }

    public function testSchedulingShowsTheMomentOnTheChip(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet');

        $this->schedule($client, '/notes/'.$noteId, 'note:'.$noteId, 'Le sommeil comme sujet', '2026-12-24', '18:30');

        $chip = $client->request('GET', '/notes/'.$noteId)->filter('.fx-reminder-chip');

        self::assertStringContainsString('fx-reminder-chip--set', (string) $chip->attr('class'));
        self::assertStringContainsString('déc. 18:30', $chip->text());
        self::assertSame('2026-12-24', $chip->attr('data-fx-remind-date'));
        self::assertSame('18:30', $chip->attr('data-fx-remind-time'));
    }

    public function testASubjectNeverCarriesTwoReminders(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet');
        $subject = 'note:'.$noteId;

        $this->schedule($client, '/notes/'.$noteId, $subject, 'Le sommeil', '2026-12-24', '18:30');
        $this->schedule($client, '/notes/'.$noteId, $subject, 'Le sommeil', '2026-12-31', '09:00');

        $chips = $client->request('GET', '/notes/'.$noteId)->filter('.fx-reminder-chip');

        self::assertCount(1, $chips);
        self::assertSame('2026-12-31', $chips->attr('data-fx-remind-date'));
    }

    public function testATaskCarriesItsOwnReminder(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $listId = $this->openList($client, 'Cette semaine');

        $this->createLiveComponent('TaskChecklist', ['listId' => $listId], $client)
            ->actingAs($account)
            ->set('draft', 'Sortir le vélo')
            ->call('add');

        $crawler = $client->request('GET', '/taches/'.$listId);
        $subject = (string) $crawler->filter('.fx-reminder-chip')->attr('data-fx-remind-subject');

        self::assertStringStartsWith('task:', $subject);

        $this->schedule($client, '/taches/'.$listId, $subject, 'Sortir le vélo', '2026-12-24', '18:30');

        self::assertStringContainsString(
            'déc. 18:30',
            $client->request('GET', '/taches/'.$listId)->filter('.fx-reminder-chip')->text(),
        );
    }

    public function testTheCalendarFileCarriesTheMomentInUtc(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet');

        $this->schedule($client, '/notes/'.$noteId, 'note:'.$noteId, 'Le sommeil', '2026-12-24', '18:30');

        $href = (string) $client->request('GET', '/notes/'.$noteId)
            ->filter('.fx-reminder-chip')
            ->attr('data-fx-remind-ics');

        $client->request('GET', $href);
        $body = (string) $client->getResponse()->getContent();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/calendar', (string) $client->getResponse()->headers->get('Content-Type'));
        // 18 h 30 à Paris en décembre, c'est 17 h 30 UTC.
        self::assertStringContainsString('DTSTART:20261224T173000Z', $body);
        self::assertStringContainsString('SUMMARY:Le sommeil', $body);
    }

    public function testRemovingAReminderPutsTheChipBackToItsOffer(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet');
        $subject = 'note:'.$noteId;

        $this->schedule($client, '/notes/'.$noteId, $subject, 'Le sommeil', '2026-12-24', '18:30');

        $client->request('POST', '/rappels/supprimer', [
            'subject' => $subject,
            '_token' => $this->token($client, '/notes/'.$noteId, '/rappels/supprimer'),
        ], server: ['HTTP_REFERER' => '/notes/'.$noteId]);
        $client->followRedirect();

        self::assertSame('Rappel', trim($client->request('GET', '/notes/'.$noteId)->filter('.fx-reminder-chip')->text()));
    }

    public function testAMomentThatIsNotOneIsRefused(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet');

        $this->schedule($client, '/notes/'.$noteId, 'note:'.$noteId, 'Le sommeil', 'pas-une-date', '18:30');

        self::assertStringNotContainsString(
            'fx-reminder-chip--set',
            (string) $client->request('GET', '/notes/'.$noteId)->filter('.fx-reminder-chip')->attr('class'),
        );
    }

    public function testAReminderOfAnotherAccountIsOutOfReach(): void
    {
        $client = self::createClient();
        $this->logIn($client, 'autre@focusyn.fr');
        $noteId = $this->writeNote($client, 'Note de quelqu’un d’autre');
        $this->schedule($client, '/notes/'.$noteId, 'note:'.$noteId, 'Ailleurs', '2026-12-24', '18:30');
        $href = (string) $client->request('GET', '/notes/'.$noteId)
            ->filter('.fx-reminder-chip')
            ->attr('data-fx-remind-ics');
        $this->signOut($client);

        $this->logIn($client);
        $client->request('GET', $href);

        self::assertResponseStatusCodeSame(404);
    }

    private function schedule(
        KernelBrowser $client,
        string $from,
        string $subject,
        string $label,
        string $date,
        string $time,
    ): void {
        $client->request('POST', '/rappels', [
            'subject' => $subject,
            'label' => $label,
            'date' => $date,
            'time' => $time,
            '_token' => $this->token($client, $from, '/rappels'),
        ], server: ['HTTP_REFERER' => $from]);
        $client->followRedirect();
    }

    private function token(KernelBrowser $client, string $page, string $action): string
    {
        return (string) $client->request('GET', $page)
            ->filter('form[action="'.$action.'"] input[name="_token"]')
            ->first()
            ->attr('value');
    }

    private function openList(KernelBrowser $client, string $name): string
    {
        $crawler = $client->request('GET', '/taches');
        $client->submit($crawler->filter('form[action="/taches/nouvelle"]')->form());
        $client->followRedirect();

        $listId = (string) $client->getRequest()->attributes->get('id');

        $client->submit($client->getCrawler()->filter('.fx-note__title-form')->form(['name' => $name]));
        $client->followRedirect();

        return $listId;
    }
}
