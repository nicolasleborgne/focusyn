<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Consent;

use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Model\SubjectId;
use App\Privacy\Domain\Repository\PrivacyChoicesRepository;
use App\Shared\Application\Privacy\ConsentGate;
use InvalidArgumentException;
use ValueError;

final readonly class StoredConsentGate implements ConsentGate
{
    public function __construct(
        private PrivacyChoicesRepository $choices,
    ) {
    }

    public function allows(string $consent, string $accountId): bool
    {
        try {
            $known = Consent::from($consent);
            $subject = SubjectId::fromString($accountId);
        } catch (ValueError|InvalidArgumentException) {
            // Un consentement qu'on ne sait pas nommer n'a pas été donné.
            return false;
        }

        return $this->choices->ofSubject($subject)?->allows($known) ?? false;
    }
}
