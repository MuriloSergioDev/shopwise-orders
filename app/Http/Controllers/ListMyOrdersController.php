<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListOrdersRequest;
use App\Http\Resources\OrderPageResource;
use App\Queries\ListOrders;
use Shopwise\Kit\Auth\JwtUser;

final class ListMyOrdersController
{
    public function __invoke(ListOrdersRequest $request, ListOrders $listOrders): OrderPageResource
    {
        $user = $request->user();
        abort_unless($user instanceof JwtUser, 401);

        return OrderPageResource::make($listOrders->handle($request->toData(customerId: $user->id)));
    }
}
