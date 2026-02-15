<?php

namespace App\Observers;

use App\Models\Product;
use App\Jobs\SyncProductToIndex;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        SyncProductToIndex::dispatch($product, 'index');
        // invalidate caches
        Cache::forget("product:{$product->id}");
        Cache::tags(['products_search'])->flush();
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        SyncProductToIndex::dispatch($product, 'update');
        // invalidate caches
        Cache::forget("product:{$product->id}");
        Cache::tags(['products_search'])->flush();
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        SyncProductToIndex::dispatch($product->id, 'delete');
        // invalidate caches
        Cache::forget("product:{$product->id}");
        Cache::tags(['products_search'])->flush();
    }
}
