<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Foreign key to products, cascade delete is safe
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            // Foreign key to holds, no cascade to avoid SQL Server error
            $table->foreignId('hold_id')->nullable()->constrained()->onDelete('no action');

            // Foreign key to users, cascade delete is safe
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->integer('qty');
            $table->integer('total'); // price in cents
            $table->string('status')->default('pending');
            $table->string('payment_idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index('status');
            $table->index('user_id');
            $table->index('hold_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
