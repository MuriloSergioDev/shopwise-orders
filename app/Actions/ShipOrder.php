<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Exceptions\OrderStatusConflict;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Shopwise\Contracts\Events\Orders\OrderShippedV1;
use Shopwise\Kit\Messaging\Outbox;

final readonly class ShipOrder
{
    /**
     * Marks the order shipped and records `orders.order-shipped`. Repeating it with the same carrier and tracking
     * number changes nothing and records nothing; anything else the status doesn't allow is a conflict (409).
     */
    public function handle(string $orderId, string $carrier, string $trackingNumber): Order
    {
        return DB::transaction(function () use ($orderId, $carrier, $trackingNumber): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

            if ($order->isShippedWith($carrier, $trackingNumber)) {
                return $order;
            }

            if (! $order->status->canTransitionTo(OrderStatus::Shipped)) {
                throw new OrderStatusConflict($order->status === OrderStatus::Shipped
                    ? "Order {$order->number} was already shipped with a different carrier or tracking number."
                    : "Order {$order->number} is {$order->status->value} and cannot be shipped.");
            }

            $order->status = OrderStatus::Shipped;
            $order->carrier = $carrier;
            $order->tracking_number = $trackingNumber;
            $order->shipped_at = now();
            $order->save();

            Outbox::record(OrderShippedV1::TYPE, $order->id, $order->shippedEvent()->toArray(), OrderShippedV1::SCHEMA);

            return $order;
        });
    }
}
