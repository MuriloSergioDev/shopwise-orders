<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;

it('lists every order newest first', function (): void {
    $older = Order::factory()->create(['placed_at' => now()->subHour()]);
    $newer = Order::factory()->ownedBy(CUSTOMER_ID)->create();

    $this->getJson('/api/admin/orders', adminBearer())->assertOk()
        ->assertJsonPath('data.*.id', [$newer->id, $older->id])
        ->assertJsonPath('meta.total', 2);
});

it('filters by status', function (): void {
    Order::factory()->create();
    $shipped = Order::factory()->shipped()->create();

    $this->getJson('/api/admin/orders?status=shipped', adminBearer())->assertOk()->assertJsonPath('data.*.id', [$shipped->id]);
    $cancelled = Order::factory()->create(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);

    $this->getJson('/api/admin/orders?status=cancelled', adminBearer())->assertOk()
        ->assertJsonPath('data.*.id', [$cancelled->id])
        ->assertJsonPath('data.0.status', 'cancelled');
    $this->getJson('/api/admin/orders?status=returned', adminBearer())->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('searches by order number or customer email', function (): void {
    $ada = Order::factory()->create(['email' => 'Ada.Lovelace@example.com']);
    $grace = Order::factory()->create(['email' => 'grace@example.com']);

    $this->getJson('/api/admin/orders?q='.mb_strtolower($grace->number), adminBearer())->assertJsonPath('data.*.id', [$grace->id]);
    $this->getJson('/api/admin/orders?q=lovelace', adminBearer())->assertJsonPath('data.*.id', [$ada->id]);
    $this->getJson('/api/admin/orders?q=example.com', adminBearer())->assertJsonCount(2, 'data');
    $this->getJson('/api/admin/orders?q=%25', adminBearer())->assertJsonCount(0, 'data');
});

it('shows any order', function (): void {
    $order = Order::factory()->create();

    $this->getJson("/api/admin/orders/{$order->id}", adminBearer())->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.lines.0.sku', 'TEE-MER-BLA-M');
    $this->getJson('/api/admin/orders/01JBCDEFGHJKMNPQRSTVWXYZ00', adminBearer())->assertNotFound();
});

it('needs an admin token', function (): void {
    $order = Order::factory()->ownedBy(CUSTOMER_ID)->create();

    $this->getJson('/api/admin/orders')->assertUnauthorized();
    $this->getJson('/api/admin/orders', bearer())->assertForbidden();
    $this->getJson("/api/admin/orders/{$order->id}", bearer())->assertForbidden();
    $this->putJson("/api/admin/orders/{$order->id}/shipment", ['carrier' => 'UPS', 'trackingNumber' => '1'], bearer())->assertForbidden();

    expect($order->refresh()->shipped_at)->toBeNull();
});
