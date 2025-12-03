<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hold extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'qty',
        'expires_at',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->hasOne(Order::class, 'hold_id');
    }

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }
}
