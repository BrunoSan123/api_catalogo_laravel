<?php

namespace App\Repository\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface
{
    public function all(): Collection;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function find(string $id): ?Product;

    public function findBySku(string $sku): ?Product;

    public function create(array $data): Product;

    public function update(string $id, array $data): bool;

    public function delete(string $id): bool;

    public function search(string $term, int $perPage = 15): LengthAwarePaginator;
}
