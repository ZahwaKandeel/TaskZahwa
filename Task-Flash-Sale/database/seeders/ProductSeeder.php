<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::create([
            'name' => 'Flash Sale Product',
            'price' => 159900,        // in cents
            'total_stock' => 10,      // total stock
            'available_stock' => 10,  // current available stock
        ]);
    }
}
