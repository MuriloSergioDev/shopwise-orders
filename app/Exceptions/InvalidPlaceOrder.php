<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An `orders.place-order` command that breaks its schema or whose amounts don't add up. Answered with
 * `orders.order-rejected` (reason `invalid`), never retried.
 */
final class InvalidPlaceOrder extends RuntimeException {}
