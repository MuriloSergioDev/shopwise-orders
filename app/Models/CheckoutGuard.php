<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-checkout lock and cancellation tombstone. `orders.place-order` and `orders.cancel-order` arrive on different
 * topics, so they can be handled concurrently; both take this row's lock first so one sees the other's outcome.
 *
 * @property string $checkout_id
 * @property string|null $cancel_reason
 * @property Carbon|null $cancelled_at
 */
#[WithoutTimestamps]
#[WithoutIncrementing]
final class CheckoutGuard extends Model
{
    protected $primaryKey = 'checkout_id';

    protected $keyType = 'string';

    /**
     * Creates the row if needed and locks it for the rest of the current transaction. On Postgres a concurrent
     * insert of the same key waits for the other transaction, so the two commands are serialized.
     */
    public static function lock(string $checkoutId): self
    {
        DB::table('checkout_guards')->insertOrIgnore(['checkout_id' => $checkoutId]);

        return self::query()->lockForUpdate()->findOrFail($checkoutId);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
        ];
    }
}
