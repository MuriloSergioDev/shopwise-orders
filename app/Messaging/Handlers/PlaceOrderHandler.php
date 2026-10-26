<?php

declare(strict_types=1);

namespace App\Messaging\Handlers;

use App\Actions\PlaceOrder;
use Shopwise\Kit\Messaging\Envelope;
use Shopwise\Kit\Messaging\MessageHandler;

/**
 * Checkout's `orders.place-order` command. The action replies through the outbox in the same transaction.
 */
final readonly class PlaceOrderHandler implements MessageHandler
{
    public function __construct(private PlaceOrder $placeOrder) {}

    public function handle(Envelope $envelope): void
    {
        $this->placeOrder->handle($envelope->data, $envelope->subject);
    }
}
