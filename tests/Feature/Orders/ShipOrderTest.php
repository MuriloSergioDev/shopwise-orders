<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;

it('marks the order shipped and records order-shipped', function (): void {
    $order = Order::factory()->create(['email' => 'ada@example.com']);

    $this->putJson("/api/admin/orders/{$order->id}/shipment", ['carrier' => 'UPS', 'trackingNumber' => '1Z999'], adminBearer())
        ->assertOk()
        ->assertJsonPath('data.status', 'shipped')
        ->assertJsonPath('data.shipment.carrier', 'UPS')
        ->assertJsonPath('data.shipment.trackingNumber', '1Z999')
        ->assertJsonPath('data.lines.0.sku', 'TEE-MER-BLA-M');

    $order->refresh();
    $shipped = recordedEvents('orders.order-shipped');
    expect($order->status)->toBe(OrderStatus::Shipped)
        ->and($shipped)->toHaveCount(1)
        ->and($shipped[0]['subject'])->toBe($order->id)
        ->and($shipped[0]['data'])->toEqualCanonicalizing([
            'orderId' => $order->id,
            'number' => $order->number,
            'customerId' => $order->customer_id,
            'email' => 'ada@example.com',
            'carrier' => 'UPS',
            'trackingNumber' => '1Z999',
            'shippedAt' => $order->shipped_at?->utc()->toIso8601ZuluString(),
        ]);
});

it('treats the same shipment again as a no-op', function (): void {
    $order = Order::factory()->create();
    $body = ['carrier' => 'UPS', 'trackingNumber' => '1Z999'];

    $this->putJson("/api/admin/orders/{$order->id}/shipment", $body, adminBearer())->assertOk();
    $shippedAt = $order->refresh()->shipped_at;
    $this->travel(1)->hour();

    $this->putJson("/api/admin/orders/{$order->id}/shipment", $body, adminBearer())->assertOk()
        ->assertJsonPath('data.shipment.shippedAt', $shippedAt?->utc()->toIso8601ZuluString());
    expect(recordedEvents('orders.order-shipped'))->toHaveCount(1);
});

it('refuses to re-ship with other details', function (array $body): void {
    $order = Order::factory()->shipped('UPS', '1Z999')->create();

    $this->putJson("/api/admin/orders/{$order->id}/shipment", $body, adminBearer())->assertConflict()
        ->assertJsonPath('message', "Order {$order->number} was already shipped with a different carrier or tracking number.");

    expect($order->refresh()->only(['carrier', 'tracking_number']))->toBe(['carrier' => 'UPS', 'tracking_number' => '1Z999'])
        ->and(recordedEvents('orders.order-shipped'))->toBe([]);
})->with([
    'other carrier' => [['carrier' => 'DHL', 'trackingNumber' => '1Z999']],
    'other tracking number' => [['carrier' => 'UPS', 'trackingNumber' => '1Z000']],
]);

it('validates the shipment', function (): void {
    $order = Order::factory()->create();

    $this->putJson("/api/admin/orders/{$order->id}/shipment", ['carrier' => '', 'trackingNumber' => str_repeat('9', 101)], adminBearer())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['carrier', 'trackingNumber']);
});

it('answers 404 for an unknown order', function (): void {
    $this->putJson('/api/admin/orders/01JBCDEFGHJKMNPQRSTVWXYZ00/shipment', ['carrier' => 'UPS', 'trackingNumber' => '1'], adminBearer())
        ->assertNotFound();
});

it('only lets a status move forward', function (): void {
    expect(OrderStatus::Placed->canTransitionTo(OrderStatus::Shipped))->toBeTrue()
        ->and(OrderStatus::Shipped->canTransitionTo(OrderStatus::Placed))->toBeFalse()
        ->and(OrderStatus::Shipped->canTransitionTo(OrderStatus::Shipped))->toBeFalse()
        ->and(OrderStatus::Placed->canTransitionTo(OrderStatus::Placed))->toBeFalse();
});
