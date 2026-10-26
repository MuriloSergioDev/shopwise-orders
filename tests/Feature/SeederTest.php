<?php

declare(strict_types=1);

use App\Models\Order;
use Database\Seeders\DatabaseSeeder;

it('seeds demo orders once, without recording events', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Order::query()->count())->toBe(3)
        ->and(Order::query()->whereNotNull('shipped_at')->count())->toBe(1)
        ->and(recordedEvents('orders.order-placed'))->toBe([]);
});
