# shopwise-orders

Orders for Shopwise: created by the checkout saga's `orders.place-order` command, listed for customers, shipped by
admins. Conventions: `AGENTS.md`.

Run it from `shopwise-platform`: `tilt up -- --only=identity,orders`, then

```sh
kubectl exec deploy/orders-http -- php artisan db:seed --force   # 3 demo orders, once
curl -s localhost:8108/api/admin/orders -H "Authorization: Bearer $ADMIN_TOKEN"
curl -s -X PUT localhost:8108/api/admin/orders/<id>/shipment -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' -d '{"carrier":"UPS","trackingNumber":"1Z999"}'
```

Customer endpoints (`/api/me/orders`) need the customer's access token; admin endpoints a token with the `admin`
role (`admin@shopwise.test` after seeding identity).
