<?php

declare(strict_types=1);

namespace PHPCoreLab\UpiGateway\DTOs;

final class OrderPayload
{
    public function __construct(
        public readonly string $orderId,
        public readonly int $amountPaisa,       // always in paise (₹1 = 100)
        public readonly string $currency = 'INR',
        public readonly ?string $customerName = null,
        public readonly ?string $customerPhone = null,
        public readonly ?string $customerEmail = null,
        public readonly array $meta = [], // arbitrary provider-specific extras
    ) {
    }
}
