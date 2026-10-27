<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Fulfilment status. The order's contents never change; only this moves, and only forward. `cancelled` comes only
 * from checkout's compensation (`orders.cancel-order`).
 */
enum OrderStatus: string
{
    case Placed = 'placed';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Placed => in_array($next, [self::Shipped, self::Cancelled], true),
            self::Shipped, self::Cancelled => false,
        };
    }
}
