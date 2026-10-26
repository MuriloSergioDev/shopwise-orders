<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\OrderListData;
use App\Enums\OrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class ListOrdersRequest extends FormRequest
{
    /**
     * @return array<string, list<string|ValidationRule|Enum>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', new Enum(OrderStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @param  string|null  $customerId  set for the customer endpoint, which has no status or search filter
     */
    public function toData(?string $customerId = null): OrderListData
    {
        $search = $this->string('q')->trim()->toString();

        return new OrderListData(
            customerId: $customerId,
            status: $customerId === null ? $this->enum('status', OrderStatus::class) : null,
            search: $customerId === null && $search !== '' ? $search : null,
            page: $this->integer('page', 1),
            perPage: $this->integer('perPage', 25),
        );
    }
}
