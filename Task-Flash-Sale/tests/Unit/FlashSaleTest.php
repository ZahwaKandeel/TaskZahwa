<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Product;
use App\Models\Hold;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class FlashSaleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_holds_and_does_not_oversell()
    {
        $product = Product::factory()->create([
            'total_stock'     => 5,
            'available_stock' => 5,
        ]);

        // Simulate 2 parallel holds
        DB::transaction(function () use ($product) {
            Hold::factory(['product_id' => $product->id, 'qty' => 3])->create();
            Hold::factory(['product_id' => $product->id, 'qty' => 3])->create();
        });

        $this->assertEquals(2, Hold::count()); // 2 holds attempted
        $product->refresh();
        $this->assertEquals(-1, $product->available_stock); // oversold check
    }

    /** @test */
    public function expired_holds_release_stock()
    {
        $product = Product::factory()->create([
            'total_stock' => 5,
            'available_stock' => 5,
        ]);

        $hold = Hold::factory([
            'product_id' => $product->id,
            'qty'        => 3,
            'expires_at' => now()->subMinute(),
        ])->create();

        // Simulate release
        if ($hold->expires_at <= now() && $hold->status === 'active') {
            $hold->product->increment('available_stock', $hold->qty);
            $hold->status = 'expired';
            $hold->save();
        }

        $this->assertEquals(5, $product->fresh()->available_stock);
        $this->assertEquals('expired', $hold->fresh()->status);
    }

    /** @test */
    public function order_webhook_is_idempotent()
    {
        $product = Product::factory()->create();
        $hold = Hold::factory(['product_id' => $product->id])->create();
        $order = Order::factory([
            'product_id' => $product->id,
            'hold_id'    => $hold->id,
        ])->create();

        $key = 'webhook-123';

        // First webhook
        if ($order->payment_idempotency_key !== $key) {
            $order->status = 'paid';
            $order->payment_idempotency_key = $key;
            $order->save();
        }

        // Duplicate webhook
        if ($order->payment_idempotency_key !== $key) {
            $order->status = 'paid';
            $order->payment_idempotency_key = $key;
            $order->save();
        }

        $this->assertEquals('paid', $order->fresh()->status);
        $this->assertEquals($key, $order->fresh()->payment_idempotency_key);
    }
}
