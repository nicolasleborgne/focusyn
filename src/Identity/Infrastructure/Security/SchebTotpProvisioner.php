<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\TotpProvisioner;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\TotpSecret;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use SensitiveParameter;

/**
 * Enrôlement TOTP.
 *
 * La vérification pendant l'enrôlement porte sur un secret encore *en attente*,
 * que le compte ne possède pas : on construit donc un porteur temporaire pour
 * interroger l'authentificateur, plutôt que d'écrire le secret en base avant
 * d'être sûr que l'utilisateur l'a bien enregistré dans son application.
 */
final readonly class SchebTotpProvisioner implements TotpProvisioner
{
    public function __construct(
        private TotpAuthenticatorInterface $totp,
    ) {
    }

    public function generateSecret(): TotpSecret
    {
        return TotpSecret::fromString($this->totp->generateSecret());
    }

    public function verify(EmailAddress $email, TotpSecret $secret, #[SensitiveParameter] string $code): bool
    {
        return $this->totp->checkCode($this->pendingHolder($email, $secret), trim($code));
    }

    public function provisioningUri(EmailAddress $email, TotpSecret $secret): string
    {
        return $this->totp->getQRContent($this->pendingHolder($email, $secret));
    }

    private function pendingHolder(EmailAddress $email, TotpSecret $secret): SecurityUser
    {
        $address = $email->toString();
        \assert('' !== $address);

        return new SecurityUser('en-attente', $address, '', $secret->toString());
    }
}
