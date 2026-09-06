<?php

declare(strict_types=1);

namespace App\Tests\Double\Assistant;

use App\Assistant\Application\Exception\AssistantRefused;
use App\Assistant\Application\Port\ChatCompletion;
use App\Assistant\Domain\Model\Provider;

/**
 * Le fournisseur, simulé.
 *
 * Il remplace les trois adaptateurs en environnement de test : aucun appel ne
 * doit sortir de la machine parce qu'un test tourne. Il retient ce qu'on lui a
 * envoyé — c'est justement ce qu'on veut vérifier.
 */
final class FakeCompletion implements ChatCompletion
{
    public ?string $lastPrompt = null;
    public ?string $lastKey = null;
    public ?string $lastModel = null;
    public ?string $refusal = null;
    public string $answer = "- une première puce\n- une seconde";

    public function supports(Provider $provider): bool
    {
        return true;
    }

    public function complete(string $model, string $systemPrompt, string $userPrompt, ?string $apiKey, ?string $baseUrl): string
    {
        $this->lastModel = $model;
        $this->lastPrompt = $userPrompt;
        $this->lastKey = $apiKey;

        if (null !== $this->refusal) {
            throw new AssistantRefused($this->refusal);
        }

        return $this->answer;
    }
}
