# shopwise-orders

Orders: the immutable record of what a customer bought at what price, created once per checkout by checkout's
`orders.place-order` command (ADR-0004 step 6) and cancelled only by its `orders.cancel-order` compensation, plus order history for customers and fulfilment (shipping) for admins.
Created from `shopwise-service-template`: structure, checks and how to run PHP are described there and still apply.

## Service facts
- **Owns**: `orders` (ULID id, unique `number` `SW-000123`, unique `checkout_id`, `command_hash`, `customer_id`, `email`, `status`, `currency` + `subtotal_amount`/`discount_amount`/`total_amount` in minor units, `item_count`, `coupon_code`, `payment_id`, `shipping_address` json, `carrier`/`tracking_number`/`shipped_at`, `cancelled_at`), `order_lines` (sku, names, quantity, unit/line amount, `position`), `order_numbers` (auto-increment that hands out order numbers), `checkout_guards` (one row per checkoutId: the lock both commands take first, and the cancellation tombstone `cancel_reason`/`cancelled_at`). Database `orders`.
- **Consumes** (group `orders`): consumer `place-order` → command `orders.place-order` → `App\Actions\PlaceOrder`; consumer `cancel-order` → command `orders.cancel-order` → `App\Actions\CancelOrder`.
- **Owns topics**: `orders.place-order`, `orders.cancel-order` (command topics; ADR-0004 says the receiver declares them), `orders.order-cancelled` (key checkoutId), `orders.order-placed` (key orderId), `orders.order-rejected` (key checkoutId), `orders.order-shipped` (key orderId).
- **HTTP** (BFFs only) — contract `shopwise-contracts/openapi/orders.yaml`. Customer (`auth.jwt`): `GET /api/me/orders`, `GET /api/me/orders/{id}`. Admin (`auth.jwt:admin`): `GET /api/admin/orders?status&q`, `GET /api/admin/orders/{id}`, `PUT /api/admin/orders/{id}/shipment`.
- **REST dependencies**: identity's JWKS (kit `Jwks`, `IDENTITY_JWKS_URL`).
- **Local URL**: http://localhost:8108 (Tilt port-forward).

## Rules
- Every `orders.place-order` gets exactly one reply in the same transaction:
  - no order for the checkoutId yet and the command is valid → create it, reply `order-placed`;
  - an order exists and the command's fingerprint (`command_hash`: the data without `requestedAt`, keys sorted) matches → re-publish `order-placed` for the existing order (new envelope id, same data);
  - an order exists with another fingerprint → `order-rejected` `duplicate_conflict`; the first order stays;
  - invalid → `order-rejected` `invalid` with the first problem as `message`. Invalid means: breaks the JSON schema (checked in `App\Data\PlaceOrderData`, integers are strict), or the amounts don't add up (`lineAmount = unitAmount × quantity`, `subtotal = Σ lineAmount`, `discount ≤ subtotal`, `total = subtotal − discount`). A command with no usable `checkoutId` is rejected under the envelope key.
- Event payloads are built as arrays from the models (`Order::placedEvent`, `CancelOrder`); the generated DTOs only supply `TYPE`/`SCHEMA` (and `OrderShippedV1`). Commands are validated by `App\Data\PlaceOrderData`, not `PlaceOrderV1`.
- `orders.cancel-order` (checkout's compensation when place-order timed out) always replies `orders.order-cancelled` with the same data on a repeat:
  - an order exists → status `cancelled`, reply carries its `orderId`/`number`/`customerId`/`email`;
  - no order yet → tombstone in `checkout_guards`, reply has those four fields null;
  - after either, every `orders.place-order` for the checkout (late or re-sent) gets `order-rejected` `cancelled`;
  - a shipped order is not cancelled and gets no reply (logged as an error), so checkout stays `compensating` where it is visible;
  - a reason other than `timeout` throws and goes to `orders.cancel-order.dlq`.
- Both commands lock `CheckoutGuard::lock($checkoutId)` first: they arrive on different topics, so different consumers may handle them at the same time.
- An order's contents never change. Status only moves forward: `OrderStatus::canTransitionTo` (`placed → shipped | cancelled`). Shipping again with the same carrier and tracking number is a no-op (200, no event); with other values, or on a cancelled order, it is 409 (`App\Exceptions\OrderStatusConflict` renders it).
- Customer endpoints filter by the JWT subject: another customer's order is 404. They ignore `status`/`q`.
- Admin `q` matches an order number exactly (case-insensitive) or a substring of the email (`LIKE … ESCAPE '\'`, works on SQLite and Postgres).
- Order numbers come from `order_numbers`; Postgres sequences survive test rollbacks, so tests assert the number's shape, not its value.

## Data
- `db:seed` creates 3 demo orders (one shipped) for random customers through the factory; it records no events, so notifications sends nothing. A second run does nothing.
