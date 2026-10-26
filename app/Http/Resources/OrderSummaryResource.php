<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OrderSummary in shopwise-contracts/openapi/orders.yaml.
 *
 * @property Order $resource
 */
final class OrderSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'number' => $this->resource->number,
            'status' => $this->resource->status->value,
            'customerId' => $this->resource->customer_id,
            'email' => $this->resource->email,
            'itemCount' => $this->resource->item_count,
            'currency' => $this->resource->currency,
            'total' => $this->resource->total_amount,
            'placedAt' => $this->resource->placed_at->utc()->toIso8601ZuluString(),
        ];
    }
}
