<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Hold;

class ProductController extends Controller
{
    public function show(Product $product)
    {
        // Auto-release expired holds (fire-and-forget)
        Hold::where('expires_at', '<', now())
            ->where('status', 'active')
            ->chunk(100, fn($holds) => $holds->each(function ($hold) {
                $hold->product->increment('available_stock', $hold->qty);
                $hold->update(['status' => 'expired']);
            }));

        return [
            'id'              => $product->id,
            'name'            => $product->name,
            'price'           => $product->price,
            'available_stock' => $product->fresh()->available_stock,
        ];
    }
}