<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Product;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'name' => 'Flash Sale Product',
            'price' => 159900,           // price in cents
            'total_stock' => 10,         // finite total stock
            'available_stock' => 10,     // available stock initially equals total stock
        ];
    }
}
