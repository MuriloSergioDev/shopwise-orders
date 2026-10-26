<?php

declare(strict_types=1);

use App\Models\Order;

it('lists only the caller\'s orders, newest first, paginated', function (): void {
    $older = Order::factory()->ownedBy(CUSTOMER_ID)->create(['placed_at' => now()->subDay()]);
    $newer = Order::factory()->ownedBy(CUSTOMER_ID)->create();
    Order::factory()->create();

    $this->getJson('/api/me/orders', bearer())->assertOk()
        ->assertJsonPath('data.*.id', [$newer->id, $older->id])
        ->assertJsonPath('meta', ['page' => 1, 'perPage' => 25, 'total' => 2, 'lastPage' => 1])
        ->assertJsonPath('data.0', [
            'id' => $newer->id,
            'number' => $newer->number,
            'status' => 'placed',
            'customerId' => CUSTOMER_ID,
            'email' => $newer->email,
            'itemCount' => 2,
            'currency' => 'USD',
            'total' => 5800,
            'placedAt' => $newer->placed_at->utc()->toIso8601ZuluString(),
        ]);

    $this->getJson('/api/me/orders?perPage=1&page=2', bearer())->assertOk()
        ->assertJsonPath('data.*.id', [$older->id])
        ->assertJsonPath('meta', ['page' => 2, 'perPage' => 1, 'total' => 2, 'lastPage' => 2]);
});

it('ignores the admin filters on the customer list', function (): void {
    Order::factory()->ownedBy(CUSTOMER_ID)->create();

    $this->getJson('/api/me/orders?status=shipped&q=nobody', bearer())->assertOk()->assertJsonCount(1, 'data');
});

it('validates paging', function (): void {
    $this->getJson('/api/me/orders?perPage=101', bearer())->assertUnprocessable()->assertJsonValidationErrors('perPage');
    $this->getJson('/api/me/orders?page=0', bearer())->assertUnprocessable()->assertJsonValidationErrors('page');
});

it('shows the caller\'s order with lines, amounts, address and shipment', function (): void {
    $order = Order::factory()->ownedBy(CUSTOMER_ID)->shipped('DHL', 'JD0001')->create();

    $this->getJson("/api/me/orders/{$order->id}", bearer())->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.status', 'shipped')
        ->assertJsonPath('data.checkoutId', $order->checkout_id)
        ->assertJsonPath('data.paymentId', $order->payment_id)
        ->assertJsonPath('data.lines', [['sku' => 'TEE-MER-BLA-M', 'productName' => 'Merino Tee', 'variantName' => 'Black / M', 'quantity' => 2, 'unitAmount' => 2900, 'lineAmount' => 5800]])
        ->assertJsonPath('data.subtotal', 5800)
        ->assertJsonPath('data.discount', 0)
        ->assertJsonPath('data.couponCode', null)
        ->assertJsonPath('data.shippingAddress.line2', null)
        ->assertJsonPath('data.shippingAddress.country', 'US')
        ->assertJsonPath('data.shipment', ['carrier' => 'DHL', 'trackingNumber' => 'JD0001', 'shippedAt' => $order->shipped_at?->utc()->toIso8601ZuluString()]);
});

it('has no shipment before the order ships', function (): void {
    $order = Order::factory()->ownedBy(CUSTOMER_ID)->create();

    $this->getJson("/api/me/orders/{$order->id}", bearer())->assertOk()->assertJsonPath('data.shipment', null);
});

it('answers 404 for another customer\'s order and for unknown ids', function (): void {
    $theirs = Order::factory()->create();

    $this->getJson("/api/me/orders/{$theirs->id}", bearer())->assertNotFound();
    $this->getJson('/api/me/orders/01JBCDEFGHJKMNPQRSTVWXYZ00', bearer())->assertNotFound();
});

it('needs a token', function (): void {
    $order = Order::factory()->ownedBy(CUSTOMER_ID)->create();

    $this->getJson('/api/me/orders')->assertUnauthorized();
    $this->getJson("/api/me/orders/{$order->id}")->assertUnauthorized();
    $this->getJson('/api/me/orders', ['Authorization' => 'Bearer nope'])->assertUnauthorized();
});
