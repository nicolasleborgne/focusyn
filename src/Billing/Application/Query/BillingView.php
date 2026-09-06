<?php

declare(strict_types=1);

namespace App\Billing\Application\Query;

use App\Billing\Domain\Model\Plan;
use DateTimeImmutable;

final readonly class BillingView
{
    public function __construct(
        public Plan $entitled,
        public bool $trialing,
        public DateTimeImmutable $trialEndsAt,
        public ?DateTimeImmutable $periodEndsAt,
        public bool $cancelled,
        public bool $needsAttention,
        public int $seats,
        public int $members,
        public int $memberAllowance,
        public int $notes,
        public ?int $noteAllowance,
        public bool $hasCustomerAccount,
        public bool $paymentConfigured,
    ) {
    }

    /** Combien de personnes peuvent encore entrer. */
    public function placesLeft(): int
    {
        return max(0, $this->memberAllowance - $this->members);
    }

    /** Ce qui reste avant le plafond, quand il y en a un. */
    public function notesLeft(): ?int
    {
        return null === $this->noteAllowance ? null : max(0, $this->noteAllowance - $this->notes);
    }
}
