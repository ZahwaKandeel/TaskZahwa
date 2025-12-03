<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\HoldController;
use App\Http\Controllers\Api\OrderController;

// Product endpoint
Route::get('/products/{product}', [ProductController::class, 'show']);

// Holds endpoint
Route::post('/holds', [HoldController::class, 'store']);

// Orders endpoint
Route::post('/orders', [OrderController::class, 'store']);

// Payment webhook (idempotent)
Route::post('/payments/webhook', [OrderController::class, 'webhook']);
