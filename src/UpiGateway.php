<?php

declare(strict_types=1);

namespace PHPCoreLab\UpiGateway;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PHPCoreLab\UpiGateway\Adapters\Paytm\PaytmAdapter;
use PHPCoreLab\UpiGateway\Adapters\PhonePe\PhonePeAdapter;
use PHPCoreLab\UpiGateway\Adapters\Razorpay\RazorpayAdapter;
use PHPCoreLab\UpiGateway\Contracts\UpiProviderInterface;
use PHPCoreLab\UpiGateway\Core\GatewayConfig;
use PHPCoreLab\UpiGateway\Core\PaymentPoller;
use PHPCoreLab\UpiGateway\Core\ProviderRegistry;
use PHPCoreLab\UpiGateway\DTOs\OrderPayload;
use PHPCoreLab\UpiGateway\DTOs\PaymentStatus;
use PHPCoreLab\UpiGateway\DTOs\QrResult;
use PHPCoreLab\UpiGateway\DTOs\RefundResult;
use PHPCoreLab\UpiGateway\Enums\Environment;

class UpiGateway
{
    private ProviderRegistry $registry;
    private LoggerInterface $logger;

    public function __construct(
        private readonly GatewayConfig $config,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger   = $logger ?? new NullLogger();
        $this->registry = new ProviderRegistry();
        $this->registerBuiltInAdapters();
    }

    // -------------------------------------------------------------------------
    // Core operations
    // -------------------------------------------------------------------------

    /** Generate a UPI QR code for the order using the active provider. */
    public function createQr(OrderPayload $order): QrResult
    {
        $this->logger->info('Creating QR', [
            'provider'    => $this->config->getActiveProvider(),
            'environment' => $this->config->getEnvironment()->value,
            'order_id'    => $order->orderId,
            'amount'      => $order->amountPaisa,
        ]);

        return $this->activeProvider()->generateQr($order);
    }

    /** Single status check — use when you already have your own polling logic. */
    public function checkStatus(string $transactionId): PaymentStatus
    {
        return $this->activeProvider()->checkStatus($transactionId);
    }

    /**
     * Synchronously poll until the payment reaches a terminal state.
     *
     * For queue-based flows, inject a job that calls checkStatus() on a schedule instead.
     *
     * @param callable(PaymentStatus, int): void|null $onTick Called on every poll cycle.
     */
    public function pollUntilDone(
        string $transactionId,
        int $intervalMs = 3000,
        int $maxAttempts = 20,
        string $backoff = 'linear',
        ?callable $onTick = null,
    ): PaymentStatus {
        $poller = new PaymentPoller(
            provider:    $this->activeProvider(),
            intervalMs:  $intervalMs,
            maxAttempts: $maxAttempts,
            backoff:     $backoff,
            logger:      $this->logger,
        );

        return $poller->poll($transactionId, $onTick);
    }

    /** Initiate a full or partial refund. */
    public function refund(string $transactionId, int $amountPaisa): RefundResult
    {
        return $this->activeProvider()->refund($transactionId, $amountPaisa);
    }

    /**
     * Validate and parse an inbound webhook from the active provider.
     *
     * @param array<string, string> $headers
     */
    public function handleWebhook(string $rawBody, array $headers): PaymentStatus
    {
        return $this->activeProvider()->parseWebhook($rawBody, $headers);
    }

    // -------------------------------------------------------------------------
    // Provider management
    // -------------------------------------------------------------------------

    /** Register a custom or third-party provider adapter. */
    public function registerProvider(string $name, UpiProviderInterface $provider): void
    {
        $this->registry->register($name, $provider);
    }

    /**
     * Hot-swap the active provider.
     * Persisting the change to your config store is the caller's responsibility.
     */
    public function switchProvider(string $name): void
    {
        $this->registry->resolve($name); // validates existence first
        $this->config->setActiveProvider($name);
        $this->logger->info("UPI gateway switched to provider: {$name}", [
            'environment' => $this->config->getEnvironment()->value,
        ]);
    }

    public function activeProvider(): UpiProviderInterface
    {
        return $this->registry->resolve($this->config->getActiveProvider());
    }

    public function getEnvironment(): Environment
    {
        return $this->config->getEnvironment();
    }

    /** @return string[] */
    public function availableProviders(): array
    {
        return $this->registry->registered();
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function registerBuiltInAdapters(): void
    {
        $env = $this->config->getEnvironment();

        // Each adapter receives the shared environment so it picks the right
        // base URL and credential key pair automatically.
        $this->registry->register('razorpay', new RazorpayAdapter($this->config->getProviderConfig('razorpay'), $env));
        $this->registry->register('phonepe', new PhonePeAdapter($this->config->getProviderConfig('phonepe'), $env));
        $this->registry->register('paytm', new PaytmAdapter($this->config->getProviderConfig('paytm'), $env));
    }
}
