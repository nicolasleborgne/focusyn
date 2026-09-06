<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Domain;

use App\Assistant\Domain\Model\AssistantSettings;
use App\Assistant\Domain\Model\OwnerId;
use App\Assistant\Domain\Model\Provider;
use App\Assistant\Domain\Model\SealedKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssistantSettings::class)]
final class AssistantSettingsTest extends TestCase
{
    public function testNothingIsConfiguredToBeginWith(): void
    {
        $settings = AssistantSettings::forOwner(OwnerId::generate());

        self::assertSame(Provider::Anthropic, $settings->provider());
        self::assertFalse($settings->isUsable());
        self::assertNull($settings->key());
        self::assertTrue($settings->sendsWholeNote(), 'La maquette envoie la note entière par défaut.');
    }

    public function testEachProviderProposesItsOwnModels(): void
    {
        self::assertSame('claude-sonnet-4-5', Provider::Anthropic->models()[0]);
        self::assertContains('gpt-5', Provider::OpenAI->models());
        self::assertContains('gemini-2.5-pro', Provider::Google->models());

        // Le modèle par défaut est le premier proposé : rien à choisir pour
        // commencer.
        $settings = AssistantSettings::forOwner(OwnerId::generate());
        self::assertSame('claude-sonnet-4-5', $settings->model());
    }

    public function testAKeyMakesTheAssistantUsable(): void
    {
        $settings = AssistantSettings::forOwner(OwnerId::generate());
        $settings->useKey(SealedKey::fromCipher('chiffré'));

        self::assertTrue($settings->isUsable());
    }

    public function testChangingProviderDropsTheKeyAndTheModel(): void
    {
        $settings = AssistantSettings::forOwner(OwnerId::generate());
        $settings->useKey(SealedKey::fromCipher('chiffré'));
        $settings->chooseModel('claude-haiku-4-5');

        $settings->switchTo(Provider::OpenAI);

        // Une clé Anthropic n'ouvre rien chez OpenAI, et « claude-haiku » n'y
        // existe pas : les garder ne ferait qu'échouer plus tard, à un endroit
        // où l'on ne comprendrait plus pourquoi.
        self::assertNull($settings->key());
        self::assertFalse($settings->isUsable());
        self::assertSame('gpt-5', $settings->model());
    }

    public function testAModelThatTheProviderDoesNotOfferIsRefused(): void
    {
        $settings = AssistantSettings::forOwner(OwnerId::generate());

        $this->expectExceptionMessage('« gpt-5 » n\'est pas un modèle de ce fournisseur.');
        $settings->chooseModel('gpt-5');
    }

    public function testOnlyALocalProviderTakesAnAddress(): void
    {
        $settings = AssistantSettings::forOwner(OwnerId::generate());
        $settings->switchTo(Provider::Ollama);
        $settings->reachableAt('http://localhost:11434');

        self::assertSame('http://localhost:11434', $settings->baseUrl());

        // Un fournisseur hébergé a une adresse connue : la laisser changer
        // permettrait de détourner la clé vers un serveur choisi par un tiers.
        $settings->switchTo(Provider::Anthropic);
        self::assertNull($settings->baseUrl());

        $this->expectExceptionMessage('Seul un fournisseur local prend une adresse.');
        $settings->reachableAt('http://exemple.fr');
    }

    public function testALocalProviderNeedsNoKey(): void
    {
        $settings = AssistantSettings::forOwner(OwnerId::generate());
        $settings->switchTo(Provider::Ollama);
        $settings->reachableAt('http://localhost:11434');

        self::assertTrue($settings->isUsable(), 'Ollama tourne sur la machine : il n\'y a pas de clé à donner.');
    }

    public function testTheScopeOfWhatIsSentIsAChoice(): void
    {
        $settings = AssistantSettings::forOwner(OwnerId::generate());

        $settings->sendSelectionOnly();
        self::assertFalse($settings->sendsWholeNote());

        $settings->sendWholeNote();
        self::assertTrue($settings->sendsWholeNote());
    }
}
