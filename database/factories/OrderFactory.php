<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * An order with one line (2 × 2900) and consistent amounts.
 *
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'number' => fn (): string => sprintf('SW-%06d', DB::table('order_numbers')->insertGetId([])),
            'checkout_id' => fn (): string => (string) Str::ulid(),
            'command_hash' => fn (): string => hash('sha256', Str::random()),
            'customer_id' => fn (): string => (string) Str::ulid(),
            'email' => fake()->safeEmail(),
            'status' => OrderStatus::Placed,
            'currency' => 'USD',
            'subtotal_amount' => 5800,
            'discount_amount' => 0,
            'total_amount' => 5800,
            'item_count' => 2,
            'coupon_code' => null,
            'payment_id' => fn (): string => (string) Str::ulid(),
            'shipping_address' => [
                'name' => fake()->name(),
                'line1' => fake()->streetAddress(),
                'line2' => null,
                'city' => fake()->city(),
                'region' => null,
                'postalCode' => fake()->postcode(),
                'country' => 'US',
            ],
            'placed_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order): void {
            $order->lines()->create([
                'position' => 0,
                'sku' => 'TEE-MER-BLA-M',
                'product_name' => 'Merino Tee',
                'variant_name' => 'Black / M',
                'quantity' => 2,
                'unit_amount' => 2900,
                'line_amount' => 5800,
            ]);
        });
    }

    public function ownedBy(string $customerId): self
    {
        return $this->state(['customer_id' => $customerId]);
    }

    public function shipped(string $carrier = 'UPS', string $trackingNumber = '1Z999AA10123456784'): self
    {
        return $this->state([
            'status' => OrderStatus::Shipped,
            'carrier' => $carrier,
            'tracking_number' => $trackingNumber,
            'shipped_at' => now(),
        ]);
    }
}
