<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;

// RESTful product routes
Route::post('/products', [ProductController::class, 'store']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);

// Upload image for product
Route::post('/products/{id}/image', [ProductController::class, 'uploadImage']);

// Search endpoint (Elasticsearch)
Route::get('/search/products', [ProductController::class, 'search']);
