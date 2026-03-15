<?php

declare(strict_types=1);

namespace PHPCoreLab\UpiGateway\Exceptions;

final class ProviderException extends UpiGatewayException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?array $responseBody = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct("[{$provider}] {$message}", $code, $previous);
    }
}
