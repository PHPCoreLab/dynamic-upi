<?php

declare(strict_types=1);

namespace DynamicUpi\UpiGateway\DTOs;

final class QrResult
{
    public function __construct(
        public readonly string  $transactionId,    // provider's txn reference
        public readonly string  $qrString,          // raw UPI deep-link string
        public readonly ?string $qrImageBase64 = null, // optional pre-rendered PNG (base64)
        public readonly ?string $qrImageUrl    = null,
        public readonly ?int    $expiresAt      = null, // unix timestamp
        public readonly array   $raw            = [],   // full provider response
    ) {}
}
