<?php

declare(strict_types=1);

namespace App\Messaging\Handlers;

use App\Actions\CancelOrder;
use Shopwise\Kit\Messaging\Envelope;
use Shopwise\Kit\Messaging\MessageHandler;

/**
 * Checkout's `orders.cancel-order` compensation. The action replies through the outbox in the same transaction.
 */
final readonly class CancelOrderHandler implements MessageHandler
{
    public function __construct(private CancelOrder $cancelOrder) {}

    public function handle(Envelope $envelope): void
    {
        $this->cancelOrder->handle($envelope->data, $envelope->subject);
    }
}
