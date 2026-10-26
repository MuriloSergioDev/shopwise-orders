<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OrderPage in shopwise-contracts/openapi/orders.yaml: { data, meta }.
 *
 * @property LengthAwarePaginator<int, Order> $resource
 */
final class OrderPageResource extends JsonResource
{
    /** The response already has its own top-level "data". */
    public static $wrap;

    /**
     * @return array{data: AnonymousResourceCollection, meta: array{page: int, perPage: int, total: int, lastPage: int}}
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => OrderSummaryResource::collection($this->resource->items()),
            'meta' => [
                'page' => $this->resource->currentPage(),
                'perPage' => $this->resource->perPage(),
                'total' => $this->resource->total(),
                'lastPage' => $this->resource->lastPage(),
            ],
        ];
    }
}
