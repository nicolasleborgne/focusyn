<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les deux langues sont livrées dès le départ : chaque écran a une adresse dans
 * chacune, et la langue de l'adresse gouverne celle de la page.
 */
final class LocalizedRoutingTest extends WebTestCase
{
    public function testTheFrenchPathRendersFrench(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/bibliotheque');

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $client->getRequest()->getLocale());
        self::assertStringContainsString('Bibliothèque', $crawler->filter('title')->text());
    }

    public function testTheEnglishPathRendersEnglish(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/library');

        self::assertResponseIsSuccessful();
        self::assertSame('en', $client->getRequest()->getLocale());
        self::assertStringContainsString('Library', $crawler->filter('title')->text());
    }

    public function testEachScreenIsReachableInBothLanguages(): void
    {
        $client = self::createClient();

        foreach ([['/taches', '/tasks'], ['/recherche', '/search'], ['/reglages', '/settings']] as [$fr, $en]) {
            $client->request('GET', $fr);
            self::assertResponseIsSuccessful(\sprintf('%s doit répondre.', $fr));

            $client->request('GET', $en);
            self::assertResponseIsSuccessful(\sprintf('%s doit répondre.', $en));
        }
    }

    public function testTheOfflinePageIsAlwaysReachable(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/offline');

        self::assertResponseIsSuccessful();
        self::assertCount(
            0,
            $crawler->filter('.fx-sidebar'),
            'La page de secours ne doit pas dépendre des données de la coquille.',
        );
    }
}
