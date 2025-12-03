<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'hold_id',
        'user_id',
        'qty',
        'total',
        'status',
        'payment_idempotency_key',
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function hold()
    {
        return $this->belongsTo(Hold::class);
    }

    // Mark order as paid safely
    public function markPaid(): void
    {
        if ($this->status !== 'paid') {
            $this->status = 'paid';
            $this->save();

            // Hold is no longer needed once order is paid
            if ($this->hold) {
                $this->hold->status = 'used';
                $this->hold->save();
            }
        }
    }

    // Cancel order and release hold safely
    public function cancel(): void
    {
        if ($this->status !== 'cancelled') {
            $this->status = 'cancelled';
            $this->save();

            if ($this->hold) {
                $this->hold->status = 'expired';
                $this->hold->save();

                // Restore product availability
                $this->product->increment('available_stock', $this->qty);
            }
        }
    }
}
