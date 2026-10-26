<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ShipOrder;
use App\Http\Requests\ShipOrderRequest;
use App\Http\Resources\OrderResource;

final class ShipOrderController
{
    public function __invoke(ShipOrderRequest $request, string $orderId, ShipOrder $shipOrder): OrderResource
    {
        $order = $shipOrder->handle($orderId, $request->carrier(), $request->trackingNumber());

        return OrderResource::make($order->load('lines'));
    }
}
