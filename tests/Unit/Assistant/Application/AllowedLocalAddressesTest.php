<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Application;

use App\Assistant\Application\Query\AssistantView;
use App\Assistant\Domain\Model\Provider;
use App\Assistant\Infrastructure\Provider\ConfiguredLocalAddresses;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La liste blanche des adresses de fournisseur local.
 *
 * Ce qu'elle empêche : qu'un compte fasse appeler au serveur une adresse de
 * son choix. Un fournisseur « local » tourne sur la machine de la personne,
 * que le serveur ne peut pas joindre — ce champ ne peut donc désigner que
 * l'intérieur de notre propre réseau.
 *
 * La règle est une **égalité**, et c'est délibéré : une appartenance à un
 * réseau se contourne par un nom qui résout ailleurs, un chemin se contourne
 * par `..`, une redirection se suit. Une égalité, non.
 */
final class AllowedLocalAddressesTest extends TestCase
{
    public function testAnEmptyConfigurationAllowsNothing(): void
    {
        $addresses = new ConfiguredLocalAddresses('');

        self::assertSame([], $addresses->all());
        self::assertFalse($addresses->permits('http://localhost:11434'));
        self::assertFalse($addresses->permits(''));
    }

    public function testTheListedAddressIsPermitted(): void
    {
        $addresses = new ConfiguredLocalAddresses('http://ollama:11434, http://autre:8080');

        self::assertSame(['http://ollama:11434', 'http://autre:8080'], $addresses->all());
        self::assertTrue($addresses->permits('http://ollama:11434'));
        self::assertTrue($addresses->permits('http://autre:8080'));
    }

    /** La barre oblique finale est retirée des deux côtés, sinon la liste refuserait ce qu'elle autorise. */
    public function testTheTrailingSlashDoesNotChangeTheAnswer(): void
    {
        $addresses = new ConfiguredLocalAddresses('http://ollama:11434/');

        self::assertSame(['http://ollama:11434'], $addresses->all());
        self::assertTrue($addresses->permits('http://ollama:11434'));
        self::assertTrue($addresses->permits('  http://ollama:11434/  '));
    }

    #[DataProvider('addressesThatMustBeRefused')]
    public function testWhatIsNotListedIsRefused(string $address): void
    {
        $addresses = new ConfiguredLocalAddresses('http://ollama:11434');

        self::assertFalse($addresses->permits($address));
    }

    /** @return iterable<string, array{string}> */
    public static function addressesThatMustBeRefused(): iterable
    {
        yield 'le point de métadonnées de l\'hébergeur' => ['http://169.254.169.254'];
        yield 'la base de données' => ['http://database:5432'];
        yield 'la boucle locale' => ['http://127.0.0.1:11434'];
        yield 'un autre port du même hôte' => ['http://ollama:5432'];
        yield 'un chemin ajouté' => ['http://ollama:11434/../admin'];
        yield 'un hôte préfixé' => ['http://ollama:11434.attaquant.fr'];
        yield 'des identifiants glissés dans l\'autorité' => ['http://ollama:11434@attaquant.fr'];
        yield 'un serveur tiers' => ['https://attaquant.fr'];
    }

    /**
     * Sans adresse autorisée, le fournisseur local n'est pas proposé du tout :
     * un choix qui ne peut mener qu'à un refus est pire que pas de choix.
     */
    public function testTheLocalProviderIsNotOfferedWithoutAnAllowedAddress(): void
    {
        self::assertNotContains(Provider::Ollama, self::view([])->offeredProviders());
        self::assertContains(Provider::Anthropic, self::view([])->offeredProviders());
    }

    public function testTheLocalProviderIsOfferedOnceAnAddressIsAllowed(): void
    {
        self::assertContains(Provider::Ollama, self::view(['http://ollama:11434'])->offeredProviders());
    }

    /** @param list<string> $localAddresses */
    private static function view(array $localAddresses): AssistantView
    {
        return new AssistantView(
            consented: true,
            provider: Provider::Anthropic,
            model: Provider::Anthropic->defaultModel(),
            hasKey: false,
            baseUrl: null,
            wholeNote: true,
            usable: false,
            localAddresses: $localAddresses,
        );
    }
}
