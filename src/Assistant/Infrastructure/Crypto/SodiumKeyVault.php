<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Crypto;

use App\Assistant\Application\Exception\KeyVaultUnavailable;
use App\Assistant\Application\Port\KeyVault;
use App\Assistant\Domain\Model\SealedKey;
use SensitiveParameter;
use SodiumException;

/**
 * Chiffrement authentifié (XSalsa20-Poly1305) avec un secret d'application.
 *
 * Un nonce tiré au sort par clé scellée, rangé devant le message : deux clés
 * identiques ne donnent pas deux chiffrés identiques, et une altération du
 * stockage est détectée au lieu d'être déchiffrée en n'importe quoi.
 */
final readonly class SodiumKeyVault implements KeyVault
{
    private string $secret;

    public function __construct(
        #[SensitiveParameter]
        string $secret,
    ) {
        $decoded = '' === $secret ? false : base64_decode($secret, true);

        if (false === $decoded || \SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($decoded)) {
            throw new KeyVaultUnavailable('ASSISTANT_SECRET doit être 32 octets en base64. En tirer un : console app:assistant-secret.');
        }

        $this->secret = $decoded;
    }

    public function seal(#[SensitiveParameter] string $plainKey): SealedKey
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return SealedKey::fromCipher(base64_encode($nonce.sodium_crypto_secretbox($plainKey, $nonce, $this->secret)));
    }

    public function unseal(SealedKey $key): string
    {
        $raw = base64_decode($key->cipher(), true);

        if (false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new KeyVaultUnavailable('Cette clé scellée est illisible.');
        }

        $nonce = substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        try {
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->secret);
        } catch (SodiumException) {
            $plain = false;
        }

        if (false === $plain) {
            // Secret d'application changé, ou stockage altéré. Dans les deux
            // cas la clé est perdue : le dire vaut mieux que d'appeler le
            // fournisseur avec n'importe quoi.
            throw new KeyVaultUnavailable('Cette clé ne peut plus être descellée.');
        }

        return $plain;
    }
}
