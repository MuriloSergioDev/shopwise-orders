# shopwise-orders

Orders: the immutable record of what a customer bought at what price, created once per checkout by checkout's
`orders.place-order` command (ADR-0004 step 6), plus order history for customers and fulfilment (shipping) for admins.
Created from `shopwise-service-template`: structure, checks and how to run PHP are described there and still apply.

## Service facts
- **Owns**: `orders` (ULID id, unique `number` `SW-000123`, unique `checkout_id`, `command_hash`, `customer_id`, `email`, `status`, `currency` + `subtotal_amount`/`discount_amount`/`total_amount` in minor units, `item_count`, `coupon_code`, `payment_id`, `shipping_address` json, `carrier`/`tracking_number`/`shipped_at`), `order_lines` (sku, names, quantity, unit/line amount, `position`), `order_numbers` (auto-increment that hands out order numbers). Database `orders`.
- **Consumes** (consumer `place-order`, group `orders`): command `orders.place-order` → `App\Actions\PlaceOrder`.
- **Owns topics**: `orders.place-order` (command topic; ADR-0004 says the receiver declares it), `orders.order-placed` (key orderId), `orders.order-rejected` (key checkoutId), `orders.order-shipped` (key orderId).
- **HTTP** (BFFs only) — contract `shopwise-contracts/openapi/orders.yaml`. Customer (`auth.jwt`): `GET /api/me/orders`, `GET /api/me/orders/{id}`. Admin (`auth.jwt:admin`): `GET /api/admin/orders?status&q`, `GET /api/admin/orders/{id}`, `PUT /api/admin/orders/{id}/shipment`.
- **REST dependencies**: identity's JWKS (kit `Jwks`, `IDENTITY_JWKS_URL`).
- **Local URL**: http://localhost:8108 (Tilt port-forward).

## Rules
- Every `orders.place-order` gets exactly one reply in the same transaction:
  - no order for the checkoutId yet and the command is valid → create it, reply `order-placed`;
  - an order exists and the command's fingerprint (`command_hash`: the data without `requestedAt`, keys sorted) matches → re-publish `order-placed` for the existing order (new envelope id, same data);
  - an order exists with another fingerprint → `order-rejected` `duplicate_conflict`; the first order stays;
  - invalid → `order-rejected` `invalid` with the first problem as `message`. Invalid means: breaks the JSON schema (checked in `App\Data\PlaceOrderData`, integers are strict), or the amounts don't add up (`lineAmount = unitAmount × quantity`, `subtotal = Σ lineAmount`, `discount ≤ subtotal`, `total = subtotal − discount`). A command with no usable `checkoutId` is rejected under the envelope key.
- Event payloads with nullable fields (`couponCode`, `productName`, rejection `message`) are built as arrays, not with the generated DTOs, which type those fields as non-nullable strings. `PlaceOrderV1` is not used for the same reason.
- An order's contents never change. Status only moves forward: `OrderStatus::canTransitionTo` (`placed → shipped`). Shipping again with the same carrier and tracking number is a no-op (200, no event); with other values it is 409 (`App\Exceptions\OrderStatusConflict` renders it).
- Customer endpoints filter by the JWT subject: another customer's order is 404. They ignore `status`/`q`.
- Admin `q` matches an order number exactly (case-insensitive) or a substring of the email (`LIKE … ESCAPE '\'`, works on SQLite and Postgres).
- Order numbers come from `order_numbers`; Postgres sequences survive test rollbacks, so tests assert the number's shape, not its value.

## Data
- `db:seed` creates 3 demo orders (one shipped) for random customers through the factory; it records no events, so notifications sends nothing. A second run does nothing.
