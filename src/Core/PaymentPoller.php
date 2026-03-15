<?php

declare(strict_types=1);

namespace PHPCoreLab\UpiGateway\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PHPCoreLab\UpiGateway\Contracts\UpiProviderInterface;
use PHPCoreLab\UpiGateway\DTOs\PaymentStatus;

final class PaymentPoller
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly UpiProviderInterface $provider,
        private readonly int    $intervalMs  = 3000,
        private readonly int    $maxAttempts = 20,
        private readonly string $backoff     = 'linear', // 'linear' | 'exponential'
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Synchronously poll until the payment reaches a terminal state or
     * maxAttempts is exhausted. Returns the last known PaymentStatus.
     *
     * For queue-based / async flows, call $provider->checkStatus() directly
     * from your job handler on a schedule instead of using this method.
     *
     * @param callable(PaymentStatus, int): void|null $onTick  Called on every poll cycle.
     */
    public function poll(string $transactionId, ?callable $onTick = null): PaymentStatus
    {
        $attempt = 0;
        $status  = null;

        while ($attempt < $this->maxAttempts) {
            $attempt++;
            $providerName = $this->provider->getName();
            $this->logger->debug("Polling [{$providerName}] txn={$transactionId} attempt={$attempt}/{$this->maxAttempts}");

            $status = $this->provider->checkStatus($transactionId);

            if ($onTick !== null) {
                $onTick($status, $attempt);
            }

            if ($status->isTerminal()) {
                $this->logger->info(
                    "Payment {$transactionId} reached terminal state: {$status->state->value}",
                    ['provider' => $providerName, 'attempts' => $attempt]
                );
                return $status;
            }

            usleep($this->computeSleepUs($attempt));
        }

        $this->logger->warning(
            "Polling exhausted for txn={$transactionId} after {$attempt} attempts — status still {$status?->state->value}."
        );

        // Return the last known status (still Pending)
        return $status ?? $this->provider->checkStatus($transactionId);
    }

    private function computeSleepUs(int $attempt): int
    {
        $baseUs = $this->intervalMs * 1_000;

        return match ($this->backoff) {
            'exponential' => (int) min($baseUs * (2 ** ($attempt - 1)), 30_000_000), // cap 30 s
            default       => $baseUs,
        };
    }
}
