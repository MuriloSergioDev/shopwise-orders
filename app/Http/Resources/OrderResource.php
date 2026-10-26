<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order in shopwise-contracts/openapi/orders.yaml: the summary plus lines, amounts, address and shipment.
 *
 * @property Order $resource
 */
final class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->resource;

        return OrderSummaryResource::make($order)->toArray($request) + [
            'checkoutId' => $order->checkout_id,
            'paymentId' => $order->payment_id,
            'lines' => $order->lines->map(fn (OrderLine $line): array => $line->toContract())->values()->all(),
            'subtotal' => $order->subtotal_amount,
            'discount' => $order->discount_amount,
            'couponCode' => $order->coupon_code,
            'shippingAddress' => $order->shipping_address,
            'shipment' => $order->shipped_at === null ? null : [
                'carrier' => $order->carrier,
                'trackingNumber' => $order->tracking_number,
                'shippedAt' => $order->shipped_at->utc()->toIso8601ZuluString(),
            ],
        ];
    }
}
