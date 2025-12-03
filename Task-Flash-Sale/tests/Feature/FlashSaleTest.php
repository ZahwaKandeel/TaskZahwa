<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Hold;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FlashSaleTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        // Seed a product with 10 stock
        Product::create([
            'name' => 'Flash Sale Product',
            'price' => 1000,
            'total_stock' => 10,
            'available_stock' => 10,
        ]);
    }

    /** @test */
    public function can_create_hold()
    {
        $product = Product::first();

        $response = $this->postJson('/api/holds', [
            'product_id' => $product->id,
            'qty' => 2,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('holds', [
            'product_id' => $product->id,
            'qty' => 2,
            'status' => 'active',
        ]);

        $product->refresh();
        $this->assertEquals(8, $product->available_stock);
    }

    /** @test */
    public function hold_expiry_releases_stock()
    {
        $product = Product::first();
        $hold = Hold::create([
            'product_id' => $product->id,
            'qty' => 5,
            'status' => 'active',
            'expires_at' => now()->subMinute(), // expired
        ]);

        $this->artisan('holds:release-expired');

        $hold->refresh();
        $product->refresh();

        $this->assertEquals('expired', $hold->status);
        $this->assertEquals(10, $product->available_stock);
    }

    /** @test */
    public function can_create_order_from_hold()
    {
        $product = Product::first();
        $hold = Hold::create([
            'product_id' => $product->id,
            'qty' => 3,
            'status' => 'active',
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->postJson('/api/orders', [
            'hold_id' => $hold->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['qty' => 3, 'status' => 'pending']);

        $this->assertDatabaseHas('orders', [
            'hold_id' => $hold->id,
            'qty' => 3,
        ]);
    }

    /** @test */
    public function webhook_marks_order_paid_and_is_idempotent()
    {
        $product = Product::first();
        $hold = Hold::create([
            'product_id' => $product->id,
            'qty' => 2,
            'status' => 'active',
            'expires_at' => now()->addMinutes(2),
        ]);

        $order = Order::create([
            'product_id' => $product->id,
            'hold_id' => $hold->id,
            'user_id' => 1,
            'qty' => 2,
            'total' => 2000,
            'status' => 'pending',
            'payment_idempotency_key' => 'key123',
        ]);

        $payload = [
            'order_id' => $order->id,
            'status' => 'success',
            'idempotency_key' => 'key123',
        ];

        $this->postJson('/api/payments/webhook', $payload)
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'paid']);

        // Call webhook again (idempotent)
        $this->postJson('/api/payments/webhook', $payload)
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'paid']);
    }

    /** @test */
    public function webhook_failure_restores_stock_and_expires_hold()
    {
        $product = Product::first();
        $hold = Hold::create([
            'product_id' => $product->id,
            'qty' => 2,
            'status' => 'active',
            'expires_at' => now()->addMinutes(2),
        ]);

        $order = Order::create([
            'product_id' => $product->id,
            'hold_id' => $hold->id,
            'user_id' => 1,
            'qty' => 2,
            'total' => 2000,
            'status' => 'pending',
            'payment_idempotency_key' => 'keyfail',
        ]);

        $payload = [
            'order_id' => $order->id,
            'status' => 'failure',
            'idempotency_key' => 'keyfail',
        ];

        $this->postJson('/api/payments/webhook', $payload)
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'cancelled']);

        $product->refresh();
        $hold->refresh();

        $this->assertEquals(10, $product->available_stock);
        $this->assertEquals('expired', $hold->status);
    }
}
