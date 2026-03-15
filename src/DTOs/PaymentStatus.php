<?php

declare(strict_types=1);

namespace DynamicUpi\UpiGateway\DTOs;

final class PaymentStatus
{
    public function __construct(
        public readonly string $transactionId,
        public readonly PaymentState $state,
        public readonly ?int $amountPaisa = null,
        public readonly ?string $providerRef = null, // UPI reference number (RRN)
        public readonly ?string $failureReason = null,
        public readonly array $raw = [],
    ) {
    }

    /** Returns true when no further polling is needed. */
    public function isTerminal(): bool
    {
        return $this->state !== PaymentState::Pending;
    }
}
