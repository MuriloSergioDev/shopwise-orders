<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A fulfilment change the order's current status does not allow (e.g. re-shipping with other tracking details).
 */
final class OrderStatusConflict extends RuntimeException
{
    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 409);
    }
}
