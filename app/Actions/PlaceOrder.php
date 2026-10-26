<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\OrderLineData;
use App\Data\PlaceOrderData;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidPlaceOrder;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Shopwise\Contracts\Events\Orders\OrderPlacedV1;
use Shopwise\Contracts\Events\Orders\OrderRejectedV1;
use Shopwise\Kit\Messaging\Outbox;

/**
 * Handles checkout's `orders.place-order` (ADR-0004 step 6). One order per checkoutId: a repeated command with the
 * same contents re-publishes `orders.order-placed`, different contents get `orders.order-rejected`
 * (`duplicate_conflict`), an invalid command gets `orders.order-rejected` (`invalid`). Every command gets a reply.
 */
final readonly class PlaceOrder
{
    /**
     * @param  array<string, mixed>  $command  the command's `data`
     * @param  string  $subject  the envelope key (checkoutId); used when the command's own checkoutId is unusable
     */
    public function handle(array $command, string $subject): ?Order
    {
        $checkoutId = is_string($command['checkoutId'] ?? null) && $command['checkoutId'] !== '' ? $command['checkoutId'] : $subject;
        $hash = $this->hash($command);

        return DB::transaction(function () use ($command, $checkoutId, $hash): ?Order {
            $existing = Order::query()->with('lines')->where('checkout_id', $checkoutId)->lockForUpdate()->first();
            if ($existing instanceof Order) {
                if (hash_equals($existing->command_hash, $hash)) {
                    $this->recordPlaced($existing);

                    return $existing;
                }

                $this->recordRejected($checkoutId, 'duplicate_conflict', "Checkout already has order {$existing->number} with different contents.");

                return null;
            }

            try {
                $data = PlaceOrderData::fromCommand($command);
            } catch (InvalidPlaceOrder $invalid) {
                $this->recordRejected($checkoutId, 'invalid', $invalid->getMessage());

                return null;
            }

            $order = $this->create($data, $hash);
            $this->recordPlaced($order);

            return $order;
        });
    }

    private function create(PlaceOrderData $data, string $hash): Order
    {
        $sequence = DB::table('order_numbers')->insertGetId([]);

        $order = Order::query()->create([
            'number' => sprintf('SW-%06d', $sequence),
            'checkout_id' => $data->checkoutId,
            'command_hash' => $hash,
            'customer_id' => $data->customerId,
            'email' => $data->email,
            'status' => OrderStatus::Placed,
            'currency' => $data->currency,
            'subtotal_amount' => $data->subtotal,
            'discount_amount' => $data->discount,
            'total_amount' => $data->total,
            'item_count' => $data->itemCount(),
            'coupon_code' => $data->couponCode,
            'payment_id' => $data->paymentId,
            'shipping_address' => $data->shippingAddress,
            'placed_at' => now(),
        ]);

        $order->lines()->createMany(array_map(fn (OrderLineData $line, int $position): array => [
            'position' => $position,
            'sku' => $line->sku,
            'product_name' => $line->productName,
            'variant_name' => $line->variantName,
            'quantity' => $line->quantity,
            'unit_amount' => $line->unitAmount,
            'line_amount' => $line->lineAmount,
        ], $data->lines, array_keys($data->lines)));

        return $order->load('lines');
    }

    private function recordPlaced(Order $order): void
    {
        Outbox::record(OrderPlacedV1::TYPE, $order->id, $order->placedEvent(), OrderPlacedV1::SCHEMA);
    }

    private function recordRejected(string $checkoutId, string $reason, string $message): void
    {
        // Plain array: the generated OrderRejectedV1 types the nullable `message` as a string.
        Outbox::record(OrderRejectedV1::TYPE, $checkoutId, [
            'checkoutId' => $checkoutId,
            'reason' => $reason,
            'message' => mb_substr($message, 0, 500),
            'rejectedAt' => now()->utc()->toIso8601ZuluString(),
        ], OrderRejectedV1::SCHEMA);
    }

    /**
     * Fingerprint of what was ordered: the command without `requestedAt` (a re-send may carry a new one), with
     * object keys sorted so key order doesn't matter.
     *
     * @param  array<string, mixed>  $command
     */
    private function hash(array $command): string
    {
        unset($command['requestedAt']);

        return hash('sha256', (string) json_encode(self::canonical($command), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }
}
