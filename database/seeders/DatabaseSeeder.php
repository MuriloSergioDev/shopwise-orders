<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * A few orders for the admin screens. Real orders only come from checkout; these record no events, so
     * notifications sends nothing for them. A second run does nothing.
     */
    public function run(): void
    {
        if (Order::query()->exists()) {
            return;
        }

        Order::factory()->count(2)->create();
        Order::factory()->shipped()->create();
    }
}
