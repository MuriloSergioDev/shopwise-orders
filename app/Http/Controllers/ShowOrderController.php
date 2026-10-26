<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;

final class ShowOrderController
{
    public function __invoke(string $orderId): OrderResource
    {
        return OrderResource::make(Order::query()->with('lines')->findOrFail($orderId));
    }
}
