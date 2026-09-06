<?php

declare(strict_types=1);

namespace App\Tests\Functional\Reminder;

use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Le flux auquel un agenda s'abonne.
 *
 * C'est la seule adresse publique de l'application qui serve des données : ces
 * tests vérifient que le jeton tient bien lieu de clé, et qu'en changer ferme
 * l'ancienne porte.
 */
final class CalendarFeedTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    public function testNoFeedExistsUntilItIsAskedFor(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        self::assertCount(0, $client->request('GET', '/reglages')->filter('.fx-feed__url'));
    }

    public function testAnOpenedFeedServesTheRemindersOfItsOrganization(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet');
        $this->schedule($client, '/notes/'.$noteId, 'note:'.$noteId, 'Relire la synthèse', '2026-12-24', '18:30');

        $url = $this->openFeed($client);

        // L'agenda n'a pas de session : on repart d'une requête nue.
        $this->signOut($client);
        $client->request('GET', self::pathOf($url));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/calendar', (string) $client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('SUMMARY:Relire la synthèse', (string) $client->getResponse()->getContent());
    }

    public function testRotatingTheAddressClosesTheOldOne(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $old = $this->openFeed($client);

        $new = $this->openFeed($client);
        self::assertNotSame($old, $new);

        $client->request('GET', self::pathOf($old));
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', self::pathOf($new));
        self::assertResponseIsSuccessful();
    }

    public function testAnInventedTokenOpensNothing(): void
    {
        $client = self::createClient();

        $client->request('GET', '/calendrier/'.str_repeat('a', 43).'.ics');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAFeedNeverLeaksAnotherOrganization(): void
    {
        $client = self::createClient();
        $this->logIn($client, 'autre@focusyn.fr');
        $noteId = $this->writeNote($client, 'Note de quelqu’un d’autre');
        $this->schedule($client, '/notes/'.$noteId, 'note:'.$noteId, 'Secret industriel', '2026-12-24', '18:30');
        $this->signOut($client);

        $this->logIn($client);
        $url = $this->openFeed($client);
        $this->signOut($client);

        $client->request('GET', self::pathOf($url));

        self::assertStringNotContainsString('Secret industriel', (string) $client->getResponse()->getContent());
    }

    /** Un agenda ne reçoit qu'une adresse absolue ; le client de test veut un chemin. */
    private static function pathOf(string $url): string
    {
        $path = parse_url($url, \PHP_URL_PATH);
        self::assertIsString($path);

        return $path;
    }

    private function openFeed(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/reglages');
        $client->submit($crawler->filter('form[action="/reglages/calendrier"]')->form());
        $crawler = $client->followRedirect();

        return (string) $crawler->filter('.fx-feed__url')->attr('value');
    }

    private function schedule(
        KernelBrowser $client,
        string $from,
        string $subject,
        string $label,
        string $date,
        string $time,
    ): void {
        $token = (string) $client->request('GET', $from)
            ->filter('form[action="/rappels"] input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/rappels', [
            'subject' => $subject,
            'label' => $label,
            'date' => $date,
            'time' => $time,
            '_token' => $token,
        ], server: ['HTTP_REFERER' => $from]);
        $client->followRedirect();
    }
}
