<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Fulfilment status. The order's contents never change; only this moves, and only forward.
 */
enum OrderStatus: string
{
    case Placed = 'placed';
    case Shipped = 'shipped';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Placed => $next === self::Shipped,
            self::Shipped => false,
        };
    }
}
