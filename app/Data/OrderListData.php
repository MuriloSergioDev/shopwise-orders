<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\OrderStatus;

final readonly class OrderListData
{
    /**
     * @param  string|null  $customerId  only this customer's orders (customer endpoints); null lists everyone's
     * @param  string|null  $search  an order number or (part of) a customer email
     */
    public function __construct(
        public ?string $customerId,
        public ?OrderStatus $status,
        public ?string $search,
        public int $page,
        public int $perPage,
    ) {}
}
