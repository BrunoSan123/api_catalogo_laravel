<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repository\Contracts\ProductRepositoryInterface;
use App\Repository\Contracts\UserRepositoryInterface;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Models\Product;
use App\Observers\ProductObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Product::observe(ProductObserver::class);
    }
}
