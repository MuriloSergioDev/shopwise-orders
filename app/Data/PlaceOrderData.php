<?php

declare(strict_types=1);

namespace App\Data;

use App\Exceptions\InvalidPlaceOrder;
use Illuminate\Support\Facades\Validator;

/**
 * A validated `orders.place-order` command (shopwise-contracts/schemas/commands/orders/place-order.v1.json).
 * Built here rather than with the generated `PlaceOrderV1`, which types the nullable `couponCode` as a string.
 */
final readonly class PlaceOrderData
{
    /**
     * @param  list<OrderLineData>  $lines
     * @param  array{name: string, line1: string, line2: string|null, city: string, region: string|null, postalCode: string, country: string}  $shippingAddress
     */
    public function __construct(
        public string $checkoutId,
        public string $customerId,
        public string $email,
        public array $lines,
        public string $currency,
        public int $subtotal,
        public int $discount,
        public int $total,
        public ?string $couponCode,
        public string $paymentId,
        public array $shippingAddress,
    ) {}

    /**
     * @param  array<string, mixed>  $command
     *
     * @throws InvalidPlaceOrder when the command breaks the schema or its amounts don't add up
     */
    public static function fromCommand(array $command): self
    {
        $validator = Validator::make($command, [
            'checkoutId' => ['required', 'string', 'max:26'],
            'customerId' => ['required', 'string', 'max:26'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'lines' => ['required', 'list', 'min:1', 'max:50'],
            'lines.*' => ['required', 'array:sku,productName,variantName,quantity,unitAmount,lineAmount'],
            'lines.*.sku' => ['required', 'string', 'max:64'],
            'lines.*.productName' => ['present', 'nullable', 'string', 'max:200'],
            'lines.*.variantName' => ['required', 'string', 'max:200'],
            'lines.*.quantity' => ['required', 'integer:strict', 'min:1', 'max:99'],
            'lines.*.unitAmount' => ['required', 'integer:strict', 'min:0'],
            'lines.*.lineAmount' => ['required', 'integer:strict', 'min:0'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'subtotal' => ['required', 'integer:strict', 'min:0'],
            'discount' => ['required', 'integer:strict', 'min:0'],
            'total' => ['required', 'integer:strict', 'min:0'],
            'couponCode' => ['present', 'nullable', 'string', 'max:32'],
            'paymentId' => ['required', 'string', 'max:26'],
            'shippingAddress' => ['required', 'array:name,line1,line2,city,region,postalCode,country'],
            'shippingAddress.name' => ['required', 'string', 'max:100'],
            'shippingAddress.line1' => ['required', 'string', 'max:200'],
            'shippingAddress.line2' => ['present', 'nullable', 'string', 'max:200'],
            'shippingAddress.city' => ['required', 'string', 'max:100'],
            'shippingAddress.region' => ['present', 'nullable', 'string', 'max:100'],
            'shippingAddress.postalCode' => ['required', 'string', 'max:20'],
            'shippingAddress.country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'requestedAt' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            throw new InvalidPlaceOrder($validator->errors()->first());
        }

        /** @var array{checkoutId: string, customerId: string, email: string, lines: list<array{sku: string, productName: string|null, variantName: string, quantity: int, unitAmount: int, lineAmount: int}>, currency: string, subtotal: int, discount: int, total: int, couponCode: string|null, paymentId: string, shippingAddress: array{name: string, line1: string, line2: string|null, city: string, region: string|null, postalCode: string, country: string}} $valid */
        $valid = $validator->validated();

        $lines = array_map(fn (array $line): OrderLineData => new OrderLineData(
            sku: $line['sku'],
            productName: $line['productName'],
            variantName: $line['variantName'],
            quantity: $line['quantity'],
            unitAmount: $line['unitAmount'],
            lineAmount: $line['lineAmount'],
        ), $valid['lines']);

        $data = new self(
            checkoutId: $valid['checkoutId'],
            customerId: $valid['customerId'],
            email: $valid['email'],
            lines: $lines,
            currency: $valid['currency'],
            subtotal: $valid['subtotal'],
            discount: $valid['discount'],
            total: $valid['total'],
            couponCode: $valid['couponCode'],
            paymentId: $valid['paymentId'],
            shippingAddress: $valid['shippingAddress'],
        );
        $data->assertAmountsAddUp();

        return $data;
    }

    public function itemCount(): int
    {
        return array_sum(array_map(fn (OrderLineData $line): int => $line->quantity, $this->lines));
    }

    /**
     * The order is a record of money already taken, so its snapshot must be internally consistent.
     */
    private function assertAmountsAddUp(): void
    {
        foreach ($this->lines as $index => $line) {
            if ($line->lineAmount !== $line->unitAmount * $line->quantity) {
                throw new InvalidPlaceOrder("lines.{$index}.lineAmount must equal unitAmount × quantity.");
            }
        }

        if ($this->subtotal !== array_sum(array_map(fn (OrderLineData $line): int => $line->lineAmount, $this->lines))) {
            throw new InvalidPlaceOrder('subtotal must equal the sum of the line amounts.');
        }

        if ($this->discount > $this->subtotal) {
            throw new InvalidPlaceOrder('discount must not exceed the subtotal.');
        }

        if ($this->total !== $this->subtotal - $this->discount) {
            throw new InvalidPlaceOrder('total must equal subtotal minus discount.');
        }
    }
}
