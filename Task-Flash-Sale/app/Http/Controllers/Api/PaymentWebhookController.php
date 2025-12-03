<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'status' => 'required|in:success,failure',
            'idempotency_key' => 'required|string',
        ]);

        $orderId = $request->order_id;
        $status = $request->status;
        $key = $request->idempotency_key;

        // Use transaction to avoid race conditions
        return DB::transaction(function () use ($orderId, $status, $key) {
            $order = Order::lockForUpdate()->find($orderId);

            // If the key matches an existing processed order, skip
            if ($order->payment_idempotency_key === $key) {
                return response()->json([
                    'message' => 'Webhook already processed.',
                    'order_status' => $order->status
                ]);
            }

            // Save idempotency key
            $order->payment_idempotency_key = $key;
            $order->save();

            // Process payment result
            if ($status === 'success') {
                $order->markPaid();
            } else {
                $order->cancel();
            }

            return response()->json([
                'message' => 'Webhook processed successfully.',
                'order_status' => $order->status
            ]);
        });
    }
}
