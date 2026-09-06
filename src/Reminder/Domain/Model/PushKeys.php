<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Model;

use InvalidArgumentException;

/**
 * Les deux clés que le navigateur remet à l'abonnement.
 *
 * `p256dh` est sa clé publique, `auth` un secret partagé : c'est avec elles que
 * la charge est chiffrée, de sorte que le service de notification transporte le
 * message sans jamais pouvoir le lire.
 */
final readonly class PushKeys
{
    private function __construct(
        private string $publicKey,
        private string $authToken,
    ) {
    }

    public static function of(string $publicKey, string $authToken): self
    {
        foreach ([$publicKey, $authToken] as $key) {
            if (1 !== preg_match('/^[A-Za-z0-9_=-]{16,255}$/', $key)) {
                throw new InvalidArgumentException('Un abonnement doit porter ses deux clés.');
            }
        }

        return new self($publicKey, $authToken);
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    public function authToken(): string
    {
        return $this->authToken;
    }
}
