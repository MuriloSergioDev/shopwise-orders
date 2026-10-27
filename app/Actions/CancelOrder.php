<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\CheckoutGuard;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Shopwise\Contracts\Events\Orders\OrderCancelledV1;
use Shopwise\Kit\Messaging\Outbox;

/**
 * Handles checkout's `orders.cancel-order` (ADR-0004: compensation for a place-order step that timed out). Leaves the
 * checkout without a live order: cancels its order if one was placed, otherwise leaves a tombstone so a late
 * place-order is rejected. Idempotent per checkoutId: a repeat re-publishes `orders.order-cancelled` with the same data.
 */
final readonly class CancelOrder
{
    private const array REASONS = ['timeout'];

    /**
     * @param  array<string, mixed>  $command  the command's `data`
     * @param  string  $subject  the envelope key (checkoutId); used when the command's own checkoutId is unusable
     *
     * @throws InvalidArgumentException for an unknown reason (the message goes to the DLQ: there is no reply for it)
     */
    public function handle(array $command, string $subject): void
    {
        $checkoutId = is_string($command['checkoutId'] ?? null) && $command['checkoutId'] !== '' ? $command['checkoutId'] : $subject;
        $reason = $command['reason'] ?? null;
        if (! is_string($reason) || ! in_array($reason, self::REASONS, true)) {
            throw new InvalidArgumentException('orders.cancel-order needs reason "timeout".');
        }

        DB::transaction(function () use ($checkoutId, $reason): void {
            $guard = CheckoutGuard::lock($checkoutId);
            $order = Order::query()->where('checkout_id', $checkoutId)->lockForUpdate()->first();

            if (! $guard->isCancelled()) {
                if ($order instanceof Order && ! $order->status->canTransitionTo(OrderStatus::Cancelled)) {
                    // A shipped order can't be undone. No reply, so checkout stays compensating where someone sees it.
                    Log::error('orders.cancel-order for an order that cannot be cancelled', [
                        'checkoutId' => $checkoutId, 'orderId' => $order->id, 'status' => $order->status->value,
                    ]);

                    return;
                }

                $guard->cancel_reason = $reason;
                $guard->cancelled_at = now();
                $guard->save();

                if ($order instanceof Order) {
                    $order->status = OrderStatus::Cancelled;
                    $order->cancelled_at = $guard->cancelled_at;
                    $order->save();
                }
            }

            Outbox::record(OrderCancelledV1::TYPE, $checkoutId, [
                'checkoutId' => $checkoutId,
                'orderId' => $order?->id,
                'number' => $order?->number,
                'customerId' => $order?->customer_id,
                'email' => $order?->email,
                'reason' => $guard->cancel_reason,
                'cancelledAt' => $guard->cancelled_at?->utc()->toIso8601ZuluString(),
            ], OrderCancelledV1::SCHEMA);
        });
    }
}
