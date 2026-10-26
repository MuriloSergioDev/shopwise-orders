<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One bought SKU with the prices it was bought at (minor units of the order's currency).
 *
 * @property int $id
 * @property string $order_id
 * @property int $position
 * @property string $sku
 * @property string|null $product_name
 * @property string $variant_name
 * @property int $quantity
 * @property int $unit_amount
 * @property int $line_amount
 */
#[Fillable(['position', 'sku', 'product_name', 'variant_name', 'quantity', 'unit_amount', 'line_amount'])]
#[WithoutTimestamps]
final class OrderLine extends Model
{
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array{sku: string, productName: string|null, variantName: string, quantity: int, unitAmount: int, lineAmount: int}
     */
    public function toContract(): array
    {
        return [
            'sku' => $this->sku,
            'productName' => $this->product_name,
            'variantName' => $this->variant_name,
            'quantity' => $this->quantity,
            'unitAmount' => $this->unit_amount,
            'lineAmount' => $this->line_amount,
        ];
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'integer',
            'unit_amount' => 'integer',
            'line_amount' => 'integer',
        ];
    }
}
