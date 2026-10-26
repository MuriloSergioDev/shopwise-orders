<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Shopwise\Kit\Auth\JwtUser;

final class ShowMyOrderController
{
    /**
     * Another customer's order is a 404, not a 403: it doesn't reveal that the id exists.
     */
    public function __invoke(Request $request, string $orderId): OrderResource
    {
        $user = $request->user();
        abort_unless($user instanceof JwtUser, 401);

        $order = Order::query()->with('lines')->where('customer_id', $user->id)->findOrFail($orderId);

        return OrderResource::make($order);
    }
}
