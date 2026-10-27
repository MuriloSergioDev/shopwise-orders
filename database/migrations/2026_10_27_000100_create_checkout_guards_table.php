<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per checkout that sent a command: the lock that serializes place-order and cancel-order (they come
        // from different topics, so different consumers), and the cancellation tombstone.
        Schema::create('checkout_guards', function (Blueprint $table): void {
            $table->string('checkout_id', 26)->primary();
            $table->string('cancel_reason', 20)->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('cancelled_at');
        });
        Schema::dropIfExists('checkout_guards');
    }
};
