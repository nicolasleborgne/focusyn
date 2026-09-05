<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\TotpSecret;
use SensitiveParameter;

interface TotpProvisioner
{
    public function generateSecret(): TotpSecret;

    public function verify(EmailAddress $email, TotpSecret $secret, #[SensitiveParameter] string $code): bool;

    /**
     * URI `otpauth://` à encoder dans le QR code.
     */
    public function provisioningUri(EmailAddress $email, TotpSecret $secret): string;
}
