<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListOrdersRequest;
use App\Http\Resources\OrderPageResource;
use App\Queries\ListOrders;

final class ListOrdersController
{
    public function __invoke(ListOrdersRequest $request, ListOrders $listOrders): OrderPageResource
    {
        return OrderPageResource::make($listOrders->handle($request->toData()));
    }
}
