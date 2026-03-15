<?php

declare(strict_types=1);

namespace DynamicUpi\UpiGateway\Contracts;

use DynamicUpi\UpiGateway\DTOs\OrderPayload;
use DynamicUpi\UpiGateway\DTOs\PaymentStatus;
use DynamicUpi\UpiGateway\DTOs\QrResult;
use DynamicUpi\UpiGateway\DTOs\RefundResult;

interface UpiProviderInterface
{
    /**
     * Generate a UPI QR code for the given order.
     */
    public function generateQr(OrderPayload $order): QrResult;

    /**
     * Poll the current payment status from the provider.
     */
    public function checkStatus(string $transactionId): PaymentStatus;

    /**
     * Initiate a full or partial refund.
     */
    public function refund(string $transactionId, int $amountPaisa): RefundResult;

    /**
     * Validate and parse an inbound webhook payload into a normalised PaymentStatus.
     * Implementations MUST verify the provider's signature / checksum.
     *
     * @param array<string, string> $headers
     */
    public function parseWebhook(string $rawBody, array $headers): PaymentStatus;

    /**
     * Human-readable provider name used in logs and error messages.
     */
    public function getName(): string;
}
