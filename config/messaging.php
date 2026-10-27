<?php

declare(strict_types=1);

use App\Messaging\Handlers\CancelOrderHandler;
use App\Messaging\Handlers\PlaceOrderHandler;

return [
    'source' => env('SERVICE_NAME', 'orders'),

    'consumer_group' => env('KAFKA_CONSUMER_GROUP_ID', env('SERVICE_NAME', 'orders')),

    // Event type => handler. Every type listed here needs its topic in deploy/values.yaml → consumers.
    'handlers' => [
        'orders.place-order' => PlaceOrderHandler::class,
        'orders.cancel-order' => CancelOrderHandler::class,
    ],

    'max_attempts' => 3,

    'relay_batch_size' => 100,
];
