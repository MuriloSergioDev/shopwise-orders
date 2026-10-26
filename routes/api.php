<?php

declare(strict_types=1);

use App\Http\Controllers\ListMyOrdersController;
use App\Http\Controllers\ListOrdersController;
use App\Http\Controllers\ShipOrderController;
use App\Http\Controllers\ShowMyOrderController;
use App\Http\Controllers\ShowOrderController;
use Illuminate\Support\Facades\Route;

// Called only by the BFFs. Contract: shopwise-contracts/openapi/orders.yaml.
// Orders are created by checkout's orders.place-order command, never over HTTP.
Route::middleware('auth.jwt')->prefix('me')->name('me.')->group(function (): void {
    Route::get('/orders', ListMyOrdersController::class)->name('orders.index');
    Route::get('/orders/{orderId}', ShowMyOrderController::class)->name('orders.show');
});

Route::middleware('auth.jwt:admin')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/orders', ListOrdersController::class)->name('orders.index');
    Route::get('/orders/{orderId}', ShowOrderController::class)->name('orders.show');
    Route::put('/orders/{orderId}/shipment', ShipOrderController::class)->name('orders.ship');
});
