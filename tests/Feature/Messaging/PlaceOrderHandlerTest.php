<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use Shopwise\Kit\Messaging\DispatchResult;
use Shopwise\Kit\Messaging\Envelope;
use Shopwise\Kit\Messaging\MessageDispatcher;

const CHECKOUT_ID = '01JBCDEFGHJKMNPQRSTVWXYZ40';

function placeOrder(array $data = [], array $envelope = []): DispatchResult
{
    return app(MessageDispatcher::class)->dispatch(contractExample('orders.place-order.v1', $data, $envelope), 'orders.place-order');
}

it('records the order with its lines and money snapshot and replies order-placed', function (): void {
    expect(placeOrder())->toBe(DispatchResult::Handled);

    $order = Order::query()->with('lines')->sole();
    // Postgres sequences survive the test transaction rollback, so numbers are only checked for their shape.
    expect($order->number)->toMatch('/^SW-\d{6,}$/')
        ->and($order->only(['checkout_id', 'customer_id', 'email', 'currency', 'subtotal_amount', 'discount_amount', 'total_amount', 'item_count', 'coupon_code', 'payment_id']))
        ->toBe([
            'checkout_id' => CHECKOUT_ID,
            'customer_id' => CUSTOMER_ID,
            'email' => 'ada@example.com',
            'currency' => 'USD',
            'subtotal_amount' => 5800,
            'discount_amount' => 580,
            'total_amount' => 5220,
            'item_count' => 2,
            'coupon_code' => 'WELCOME10',
            'payment_id' => '01JBCDEFGHJKMNPQRSTVWXYZ43',
        ])
        ->and($order->status)->toBe(OrderStatus::Placed)
        ->and($order->shipping_address['line2'])->toBeNull()
        ->and($order->lines->sole()->only(['sku', 'product_name', 'variant_name', 'quantity', 'unit_amount', 'line_amount']))
        ->toBe(['sku' => 'TEE-MER-BLK-M', 'product_name' => 'Merino Tee', 'variant_name' => 'Black / M', 'quantity' => 2, 'unit_amount' => 2900, 'line_amount' => 5800]);

    $placed = recordedEvents('orders.order-placed');
    expect($placed)->toHaveCount(1)
        ->and($placed[0]['subject'])->toBe($order->id)
        ->and($placed[0]['data'])->toEqualCanonicalizing([
            'orderId' => $order->id,
            'number' => $order->number,
            'checkoutId' => CHECKOUT_ID,
            'customerId' => CUSTOMER_ID,
            'email' => 'ada@example.com',
            'lines' => [['sku' => 'TEE-MER-BLK-M', 'productName' => 'Merino Tee', 'variantName' => 'Black / M', 'quantity' => 2, 'unitAmount' => 2900, 'lineAmount' => 5800]],
            'currency' => 'USD',
            'subtotal' => 5800,
            'discount' => 580,
            'total' => 5220,
            'couponCode' => 'WELCOME10',
            'shippingAddress' => ['name' => 'Ada Lovelace', 'line1' => "12 St James's Square", 'line2' => null, 'city' => 'London', 'region' => null, 'postalCode' => 'SW1Y 4JH', 'country' => 'GB'],
            'placedAt' => $order->placed_at->utc()->toIso8601ZuluString(),
        ])
        ->and(recordedEvents('orders.order-rejected'))->toBe([]);
});

it('ignores a redelivered envelope', function (): void {
    expect(placeOrder())->toBe(DispatchResult::Handled)
        ->and(placeOrder())->toBe(DispatchResult::Duplicate)
        ->and(Order::query()->count())->toBe(1)
        ->and(recordedEvents('orders.order-placed'))->toHaveCount(1);
});

it('creates one order per checkout and re-publishes it when checkout re-sends the command', function (): void {
    placeOrder();
    $this->travel(30)->seconds();

    // A re-send is a new envelope; key order and requestedAt may differ.
    $resend = contractExample('orders.place-order.v1', ['requestedAt' => '2026-10-26T12:00:30Z'], ['id' => '01JBCDEFGHJKMNPQRSTVWXYZ56']);
    $reordered = $resend->toArray();
    $reordered['data'] = array_reverse($resend->data, true);
    expect(app(MessageDispatcher::class)->dispatch(Envelope::fromArray($reordered), 'orders.place-order'))->toBe(DispatchResult::Handled);

    $placed = recordedEvents('orders.order-placed');
    expect(Order::query()->count())->toBe(1)
        ->and($placed)->toHaveCount(2)
        ->and($placed[1]['data'])->toEqualCanonicalizing($placed[0]['data']);
});

it('rejects a second command for the same checkout with different contents and keeps the first order', function (): void {
    placeOrder();

    placeOrder(['total' => 5800, 'discount' => 0, 'couponCode' => null], ['id' => '01JBCDEFGHJKMNPQRSTVWXYZ57']);

    $rejected = recordedEvents('orders.order-rejected');
    expect(Order::query()->sole()->total_amount)->toBe(5220)
        ->and($rejected)->toHaveCount(1)
        ->and($rejected[0]['subject'])->toBe(CHECKOUT_ID)
        ->and($rejected[0]['data']['checkoutId'])->toBe(CHECKOUT_ID)
        ->and($rejected[0]['data']['reason'])->toBe('duplicate_conflict')
        ->and($rejected[0]['data']['message'])->toContain(Order::query()->sole()->number);
});

it('rejects an invalid command as invalid and creates nothing', function (array $data, string $message): void {
    expect(placeOrder($data))->toBe(DispatchResult::Handled);

    $rejected = recordedEvents('orders.order-rejected');
    expect(Order::query()->count())->toBe(0)
        ->and(recordedEvents('orders.order-placed'))->toBe([])
        ->and($rejected)->toHaveCount(1)
        ->and($rejected[0]['data']['reason'])->toBe('invalid')
        ->and($rejected[0]['data']['message'])->toContain($message);
})->with([
    'no lines' => [['lines' => []], 'lines'],
    'string amount' => [['total' => '5220'], 'total'],
    'lowercase currency' => [['currency' => 'usd'], 'currency'],
    'line amount off' => [['lines' => [['sku' => 'A', 'productName' => null, 'variantName' => 'One', 'quantity' => 2, 'unitAmount' => 2900, 'lineAmount' => 5000]], 'subtotal' => 5000, 'total' => 4420], 'unitAmount × quantity'],
    'subtotal off' => [['subtotal' => 6000, 'total' => 5420], 'subtotal must equal'],
    'discount above subtotal' => [['discount' => 9000, 'total' => 0], 'discount must not exceed'],
    'total off' => [['total' => 5000], 'total must equal'],
    'missing line2 key' => [['shippingAddress' => ['name' => 'Ada', 'line1' => 'x', 'city' => 'London', 'region' => null, 'postalCode' => 'X', 'country' => 'GB']], 'line2'],
]);

it('rejects a command without a usable checkoutId under the envelope key', function (): void {
    placeOrder(['checkoutId' => null]);

    $rejected = recordedEvents('orders.order-rejected');
    expect($rejected[0]['subject'])->toBe(CHECKOUT_ID)
        ->and($rejected[0]['data']['checkoutId'])->toBe(CHECKOUT_ID)
        ->and($rejected[0]['data']['reason'])->toBe('invalid');
});

it('numbers orders in sequence and keeps a null product name and coupon', function (): void {
    placeOrder();
    placeOrder([
        'checkoutId' => '01JBCDEFGHJKMNPQRSTVWXYZ41',
        'couponCode' => null,
        'discount' => 0,
        'total' => 5800,
        'lines' => [['sku' => 'TEE-MER-BLK-M', 'productName' => null, 'variantName' => 'Black / M', 'quantity' => 2, 'unitAmount' => 2900, 'lineAmount' => 5800]],
    ], ['id' => '01JBCDEFGHJKMNPQRSTVWXYZ58']);

    $second = Order::query()->with('lines')->where('checkout_id', '01JBCDEFGHJKMNPQRSTVWXYZ41')->sole();
    $first = Order::query()->where('checkout_id', CHECKOUT_ID)->sole();
    expect((int) mb_substr($second->number, 3))->toBe((int) mb_substr($first->number, 3) + 1)
        ->and($second->coupon_code)->toBeNull()
        ->and($second->lines->sole()->product_name)->toBeNull()
        ->and(recordedEvents('orders.order-placed')[1]['data']['couponCode'])->toBeNull();
});
