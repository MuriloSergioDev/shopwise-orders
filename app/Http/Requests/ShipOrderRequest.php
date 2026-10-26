<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ShipOrderRequest extends FormRequest
{
    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'carrier' => ['required', 'string', 'max:50'],
            'trackingNumber' => ['required', 'string', 'max:100'],
        ];
    }

    public function carrier(): string
    {
        return $this->string('carrier')->trim()->toString();
    }

    public function trackingNumber(): string
    {
        return $this->string('trackingNumber')->trim()->toString();
    }
}
