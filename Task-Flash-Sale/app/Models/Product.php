<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'total_stock',
        'available_stock',
    ];

    // Product has many Holds
    public function holds()
    {
        return $this->hasMany(Hold::class);
    }

    // Product has many Orders
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
