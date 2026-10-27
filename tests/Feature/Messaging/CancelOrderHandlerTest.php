<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\CheckoutGuard;
use App\Models\Order;
use Shopwise\Kit\Messaging\DispatchResult;
use Shopwise\Kit\Messaging\MessageDispatcher;
use Shopwise\Kit\Messaging\Publisher;
use Shopwise\Kit\Testing\FakePublisher;

const CANCELLED_CHECKOUT_ID = '01JBCDEFGHJKMNPQRSTVWXYZ40';

function cancelOrder(array $data = [], array $envelope = []): DispatchResult
{
    return app(MessageDispatcher::class)->dispatch(contractExample('orders.cancel-order.v1', $data, $envelope), 'orders.cancel-order');
}

it('cancels a placed order and replies order-cancelled with the order\'s fields', function (): void {
    app(MessageDispatcher::class)->dispatch(contractExample('orders.place-order.v1'), 'orders.place-order');

    expect(cancelOrder())->toBe(DispatchResult::Handled);

    $order = Order::query()->sole();
    $cancelled = recordedEvents('orders.order-cancelled');
    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancelled_at)->not->toBeNull()
        ->and($cancelled)->toHaveCount(1)
        ->and($cancelled[0]['subject'])->toBe(CANCELLED_CHECKOUT_ID)
        ->and($cancelled[0]['data'])->toEqualCanonicalizing([
            'checkoutId' => CANCELLED_CHECKOUT_ID,
            'orderId' => $order->id,
            'number' => $order->number,
            'customerId' => CUSTOMER_ID,
            'email' => 'ada@example.com',
            'reason' => 'timeout',
            'cancelledAt' => $order->cancelled_at?->utc()->toIso8601ZuluString(),
        ]);
});

it('leaves a tombstone when no order exists and rejects a late place-order as cancelled', function (): void {
    cancelOrder();

    $cancelled = recordedEvents('orders.order-cancelled');
    expect($cancelled[0]['data'])->toMatchArray(['checkoutId' => CANCELLED_CHECKOUT_ID, 'orderId' => null, 'number' => null, 'customerId' => null, 'email' => null, 'reason' => 'timeout'])
        ->and(CheckoutGuard::query()->sole()->isCancelled())->toBeTrue();

    app(MessageDispatcher::class)->dispatch(contractExample('orders.place-order.v1'), 'orders.place-order');

    $rejected = recordedEvents('orders.order-rejected');
    expect(Order::query()->count())->toBe(0)
        ->and(recordedEvents('orders.order-placed'))->toBe([])
        ->and($rejected)->toHaveCount(1)
        ->and($rejected[0]['data']['reason'])->toBe('cancelled')
        ->and($rejected[0]['data']['checkoutId'])->toBe(CANCELLED_CHECKOUT_ID);
});

it('rejects a re-sent place-order for a cancelled order instead of re-publishing it', function (): void {
    app(MessageDispatcher::class)->dispatch(contractExample('orders.place-order.v1'), 'orders.place-order');
    cancelOrder();

    app(MessageDispatcher::class)->dispatch(contractExample('orders.place-order.v1', envelope: ['id' => '01JBCDEFGHJKMNPQRSTVWXYZ59']), 'orders.place-order');

    expect(recordedEvents('orders.order-placed'))->toHaveCount(1)
        ->and(recordedEvents('orders.order-rejected')[0]['data']['reason'])->toBe('cancelled');
});

it('is idempotent per checkout: a repeat re-publishes the same outcome', function (): void {
    app(MessageDispatcher::class)->dispatch(contractExample('orders.place-order.v1'), 'orders.place-order');
    cancelOrder();
    $this->travel(5)->minutes();

    expect(cancelOrder())->toBe(DispatchResult::Duplicate)
        ->and(cancelOrder(envelope: ['id' => '01JBCDEFGHJKMNPQRSTVWXYZ68']))->toBe(DispatchResult::Handled);

    $cancelled = recordedEvents('orders.order-cancelled');
    expect($cancelled)->toHaveCount(2)
        ->and($cancelled[1]['data'])->toEqualCanonicalizing($cancelled[0]['data']);
});

it('does not cancel a shipped order and sends no reply', function (): void {
    Order::factory()->shipped()->create(['checkout_id' => CANCELLED_CHECKOUT_ID]);

    expect(cancelOrder())->toBe(DispatchResult::Handled)
        ->and(Order::query()->sole()->status)->toBe(OrderStatus::Shipped)
        ->and(CheckoutGuard::query()->sole()->isCancelled())->toBeFalse()
        ->and(recordedEvents('orders.order-cancelled'))->toBe([]);
});

it('dead-letters a cancel-order with an unknown reason', function (): void {
    $this->app->instance(Publisher::class, $publisher = new FakePublisher);

    expect(app(MessageDispatcher::class)->dispatch(contractExample('orders.cancel-order.v1', ['reason' => 'changed_mind']), 'orders.cancel-order', backoffMs: 0))
        ->toBe(DispatchResult::DeadLettered)
        ->and(recordedEvents('orders.order-cancelled'))->toBe([]);
    $publisher->assertPublished('orders.cancel-order.dlq');
});
