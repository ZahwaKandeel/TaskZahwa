<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\Hold;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $product = Product::factory()->create();

        return [
            'product_id' => $product->id,
            'hold_id'    => Hold::factory(['product_id' => $product->id]),
            'user_id'    => 1,  // test user
            'qty'        => 1,
            'total'      => $product->price,
            'status'     => 'pending',
            'payment_idempotency_key' => $this->faker->uuid(),
        ];
    }
}
