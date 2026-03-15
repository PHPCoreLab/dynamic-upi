<?php

namespace PHPCoreLab\UpiGateway\DTOs;

enum PaymentState: string
{
    case Pending = 'PENDING';
    case Success = 'SUCCESS';
    case Failed = 'FAILED';
    case Expired = 'EXPIRED';
    case Refunded = 'REFUNDED';
}
