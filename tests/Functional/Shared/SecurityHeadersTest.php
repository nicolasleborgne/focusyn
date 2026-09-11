<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Tests\Functional\LogsIn;
use DOMElement;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Les en-têtes de sécurité, et surtout ce qu'ils ne contiennent pas.
 *
 * Un CSP se dégrade sans bruit : il suffit qu'un `'unsafe-inline'` revienne
 * dans `script-src` — par un réglage du bundle, par un repli « au cas où » —
 * pour que la protection disparaisse sans qu'aucun écran ne change
 * d'apparence. C'est exactement le genre de règle qu'aucune relecture ne
 * rattrape et qu'un test attrape à tous les coups.
 */
final class SecurityHeadersTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    public function testTheScriptPolicyAdmitsOnlyTheRequestNonce(): void
    {
        $client = self::createClient();
        $client->request('GET', '/connexion');

        $policy = self::directives($client->getResponse()->headers->get('Content-Security-Policy'));

        self::assertArrayHasKey('script-src', $policy);
        self::assertStringNotContainsString("'unsafe-inline'", $policy['script-src']);
        self::assertStringNotContainsString("'unsafe-eval'", $policy['script-src']);
        // `data:` dans `script-src` annulerait le nonce : un `<script
        // src="data:…">` injecté serait alors accepté sans en porter aucun.
        self::assertStringNotContainsString('data:', $policy['script-src']);
        self::assertMatchesRegularExpression("/'nonce-[A-Za-z0-9+\/=]+'/", $policy['script-src']);
    }

    public function testTheNoncePublishedInTheHeaderIsTheOneCarriedByTheScripts(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/connexion');

        $policy = self::directives($client->getResponse()->headers->get('Content-Security-Policy'));
        self::assertSame(1, preg_match("/'nonce-([^']+)'/", $policy['script-src'], $matches));

        $scripts = $crawler->filter('script');
        self::assertGreaterThan(0, $scripts->count(), 'La page ne rend aucun script.');

        foreach ($scripts as $script) {
            self::assertInstanceOf(DOMElement::class, $script);
            self::assertSame(
                $matches[1],
                $script->getAttribute('nonce'),
                'Un script en ligne sans le nonce de la requête ne s\'exécutera pas.',
            );
        }
    }

    /**
     * Rien ne doit plus passer par une adresse `data:` dans l'importmap : c'est
     * ce que faisait la feuille de style importée depuis le JavaScript, et cela
     * obligeait à ouvrir `script-src` à `data:`.
     */
    public function testNoScriptIsServedFromADataUrl(): void
    {
        $client = self::createClient();
        $client->request('GET', '/connexion');

        self::assertStringNotContainsString(
            'data:application/javascript',
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testTheDocumentPolicyDeniesWhatIsNotNamed(): void
    {
        $client = self::createClient();
        $client->request('GET', '/connexion');

        $policy = self::directives($client->getResponse()->headers->get('Content-Security-Policy'));

        self::assertSame("'none'", $policy['default-src']);
        self::assertSame("'none'", $policy['frame-ancestors']);
        self::assertSame("'none'", $policy['base-uri']);
        self::assertSame("'self'", $policy['form-action']);
        self::assertSame("'self'", $policy['connect-src']);
    }

    public function testTheOtherHeadersAreThere(): void
    {
        $client = self::createClient();
        $client->request('GET', '/connexion');

        $headers = $client->getResponse()->headers;

        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $headers->get('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $headers->get('Referrer-Policy'));
        self::assertSame('same-origin', $headers->get('Cross-Origin-Opener-Policy'));
        self::assertSame('same-origin', $headers->get('Cross-Origin-Resource-Policy'));
        self::assertStringContainsString('camera=()', (string) $headers->get('Permissions-Policy'));
    }

    /**
     * Le CSP doit couvrir les écrans de l'application, et pas seulement la
     * page de connexion : le nonce vient du gabarit de base, et une coquille
     * qui l'oublierait ne casserait qu'une fois en production.
     */
    public function testTheApplicationScreensCarryThePolicyToo(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $policy = self::directives($client->getResponse()->headers->get('Content-Security-Policy'));
        self::assertMatchesRegularExpression("/'nonce-[A-Za-z0-9+\/=]+'/", $policy['script-src']);
    }

    /**
     * Le point de collecte doit accepter le rapport du navigateur tel qu'il
     * l'envoie : sans session, sans jeton, et en `application/csp-report`.
     * Pointé vers un écran qui redirige vers la connexion, `report-uri`
     * avalerait silencieusement toutes les violations — et l'on croirait
     * qu'il n'y en a aucune.
     */
    public function testTheViolationReportEndpointAcceptsWhatTheBrowserSends(): void
    {
        $client = self::createClient();
        $client->request(
            'POST',
            '/csp/rapport',
            server: ['CONTENT_TYPE' => 'application/csp-report'],
            content: json_encode([
                'csp-report' => [
                    'document-uri' => 'https://focusyn.fr/',
                    'violated-directive' => 'script-src',
                    'effective-directive' => 'script-src',
                    'blocked-uri' => 'inline',
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(204);
    }

    /** @return array<string, string> */
    private static function directives(?string $header): array
    {
        self::assertNotNull($header, 'Aucun en-tête Content-Security-Policy.');

        $directives = [];

        foreach (explode(';', $header) as $directive) {
            $parts = preg_split('/\s+/', trim($directive), 2);

            if (false !== $parts && [] !== $parts && '' !== $parts[0]) {
                $directives[$parts[0]] = $parts[1] ?? '';
            }
        }

        return $directives;
    }
}
