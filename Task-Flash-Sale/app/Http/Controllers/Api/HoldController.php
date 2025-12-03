<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Hold;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HoldController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty'        => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request) {

            // Lock product row to prevent overselling
            $product = Product::lockForUpdate()
                ->findOrFail($request->product_id);

            // Release expired holds first
            $expiredHolds = Hold::where('product_id', $product->id)
                ->where('expires_at', '<', now())
                ->where('status', 'active')
                ->get();

            foreach ($expiredHolds as $hold) {
                $product->increment('available_stock', $hold->qty);
                $hold->update(['status' => 'expired']);
            }

            // Check if enough stock is available
            if ($request->qty > $product->available_stock) {
                return response()->json(['error' => 'Not enough stock'], 422);
            }

            // Create hold
            $hold = Hold::create([
                'product_id' => $product->id,
                'qty'        => $request->qty,
                'status'     => 'active',
                'expires_at' => now()->addMinutes(2),
            ]);

            // Reduce available stock
            $product->decrement('available_stock', $request->qty);

            return response()->json([
                'hold_id'    => $hold->id,
                'expires_at' => $hold->expires_at,
            ], 201);
        });
    }
}
