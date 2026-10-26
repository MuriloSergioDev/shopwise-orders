<?php

declare(strict_types=1);

namespace App\Queries;

use App\Data\OrderListData;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListOrders
{
    /**
     * Newest first. The search matches an order number exactly (case-insensitive) or a substring of the email.
     *
     * @return LengthAwarePaginator<int, Order>
     */
    public function handle(OrderListData $filters): LengthAwarePaginator
    {
        return Order::query()
            ->when($filters->customerId, fn (Builder $query, string $customerId) => $query->where('customer_id', $customerId))
            ->when($filters->status, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters->search, fn (Builder $query, string $search) => $query->where(
                fn (Builder $match) => $match
                    ->where('number', mb_strtoupper($search))
                    ->orWhereRaw("lower(email) like ? escape '\\'", ['%'.addcslashes(mb_strtolower($search), '%_\\').'%']),
            ))
            ->newestFirst()
            ->paginate(perPage: $filters->perPage, page: $filters->page);
    }
}
