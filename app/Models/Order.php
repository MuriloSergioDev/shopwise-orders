<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Shopwise\Contracts\Events\Orders\OrderShippedV1;

/**
 * What a customer bought, at what price, created once per checkout by `orders.place-order`. Only the fulfilment
 * status (and its shipment details) ever changes. Amounts are integer minor units of `currency`.
 *
 * @property string $id
 * @property string $number
 * @property string $checkout_id
 * @property string $command_hash
 * @property string $customer_id
 * @property string $email
 * @property OrderStatus $status
 * @property string $currency
 * @property int $subtotal_amount
 * @property int $discount_amount
 * @property int $total_amount
 * @property int $item_count
 * @property string|null $coupon_code
 * @property string $payment_id
 * @property array{name: string, line1: string, line2: string|null, city: string, region: string|null, postalCode: string, country: string} $shipping_address
 * @property string|null $carrier
 * @property string|null $tracking_number
 * @property Carbon $placed_at
 * @property Carbon|null $shipped_at
 * @property-read Collection<int, OrderLine> $lines
 */
#[Fillable([
    'number', 'checkout_id', 'command_hash', 'customer_id', 'email', 'status', 'currency', 'subtotal_amount',
    'discount_amount', 'total_amount', 'item_count', 'coupon_code', 'payment_id', 'shipping_address', 'placed_at',
])]
#[WithoutTimestamps]
final class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class)->orderBy('position');
    }

    public function isShippedWith(string $carrier, string $trackingNumber): bool
    {
        return $this->status === OrderStatus::Shipped
            && $this->carrier === $carrier
            && $this->tracking_number === $trackingNumber;
    }

    /**
     * The `orders.order-placed` payload. Also re-published when checkout repeats the command.
     *
     * @return array<string, mixed>
     */
    public function placedEvent(): array
    {
        return [
            'orderId' => $this->id,
            'number' => $this->number,
            'checkoutId' => $this->checkout_id,
            'customerId' => $this->customer_id,
            'email' => $this->email,
            'lines' => $this->lines->map(fn (OrderLine $line): array => $line->toContract())->values()->all(),
            'currency' => $this->currency,
            'subtotal' => $this->subtotal_amount,
            'discount' => $this->discount_amount,
            'total' => $this->total_amount,
            'couponCode' => $this->coupon_code,
            'shippingAddress' => $this->shipping_address,
            'placedAt' => $this->placed_at->utc()->toIso8601ZuluString(),
        ];
    }

    public function shippedEvent(): OrderShippedV1
    {
        return new OrderShippedV1(
            orderId: $this->id,
            number: $this->number,
            customerId: $this->customer_id,
            email: $this->email,
            carrier: (string) $this->carrier,
            trackingNumber: (string) $this->tracking_number,
            shippedAt: $this->shipped_at?->utc()->toIso8601ZuluString() ?? '',
        );
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function newestFirst(Builder $query): void
    {
        $query->orderByDesc('placed_at')->orderByDesc('id');
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'total_amount' => 'integer',
            'item_count' => 'integer',
            'shipping_address' => 'array',
            'placed_at' => 'datetime',
            'shipped_at' => 'datetime',
        ];
    }
}
