<?php

declare(strict_types=1);

namespace App\Data;

final readonly class OrderLineData
{
    public function __construct(
        public string $sku,
        public ?string $productName,
        public string $variantName,
        public int $quantity,
        public int $unitAmount,
        public int $lineAmount,
    ) {}
}
