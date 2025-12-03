<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Hold;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Create an order from a hold.
     */
    public function store(Request $request)
    {
        $request->validate([
            'hold_id' => 'required|exists:holds,id',
        ]);

        return DB::transaction(function () use ($request) {
            // Lock the hold row for update to prevent race conditions
            $hold = Hold::where('id', $request->hold_id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            // Prevent multiple orders from the same hold
            if ($hold->order) {
                return response()->json($hold->order, 200);
            }

            // Create order
            $order = Order::create([
                'product_id' => $hold->product_id,
                'hold_id'    => $hold->id,
                'user_id'    => 1, // replace with auth()->id() if using authentication
                'qty'        => $hold->qty,
                'total'      => $hold->qty * $hold->product->price,
                'status'     => 'pending',
                'payment_idempotency_key' => uniqid('order_', true),
            ]);

            return response()->json($order, 201);
        });
    }

    /**
     * Handle payment webhook (idempotent & out-of-order safe)
     */
    public function handle(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'status'   => 'required|in:success,failure',
            'idempotency_key' => 'required|string',
        ]);

        return DB::transaction(function () use ($request) {
            $order = Order::where('id', $request->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Check if webhook has already been applied
            if ($order->payment_idempotency_key === $request->idempotency_key &&
                in_array($order->status, ['paid', 'cancelled'])) {
                return response()->json($order, 200);
            }

            // Update order based on webhook
            if ($request->status === 'success') {
                $order->status = 'paid';
            } else {
                $order->status = 'cancelled';

                // Restore product stock if payment failed
                $order->product->increment('available_stock', $order->qty);

                // Mark hold as expired
                if ($order->hold) {
                    $order->hold->status = 'expired';
                    $order->hold->save();
                }
            }

            // Save the webhook key for idempotency
            $order->payment_idempotency_key = $request->idempotency_key;
            $order->save();

            return response()->json($order, 200);
        });
    }
}
