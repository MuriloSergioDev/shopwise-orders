<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hands out order numbers (SW-000001): an auto-increment that works the same on SQLite and Postgres.
        Schema::create('order_numbers', function (Blueprint $table): void {
            $table->id();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 20)->unique();
            $table->string('checkout_id', 26)->unique();
            $table->string('command_hash', 64);
            $table->string('customer_id', 26)->index();
            $table->string('email');
            $table->string('status', 20)->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('discount_amount');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedInteger('item_count');
            $table->string('coupon_code', 32)->nullable();
            $table->string('payment_id', 26);
            $table->json('shipping_address');
            $table->string('carrier', 50)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->timestamp('placed_at')->index();
            $table->timestamp('shipped_at')->nullable();
        });

        Schema::create('order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('sku', 64);
            $table->string('product_name', 200)->nullable();
            $table->string('variant_name', 200);
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedBigInteger('line_amount');
            $table->unique(['order_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('order_numbers');
    }
};
