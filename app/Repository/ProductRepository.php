<?php

namespace App\Repository;

use App\Models\Product;
use App\Repository\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use App\Services\ElasticsearchService;

class ProductRepository implements ProductRepositoryInterface
{
    protected Product $model;

    public function __construct(?Product $model = null)
    {
        $this->model = $model ?? new Product();
    }

    public function searchElastic(array $params): array
    {
        $es = app(ElasticsearchService::class);
        return $es->search($params);
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->paginate($perPage);
    }

    public function find(string $id): ?Product
    {
        return $this->model->find($id);
    }

    public function findBySku(string $sku): ?Product
    {
        return $this->model->where('sku', $sku)->first();
    }

    public function create(array $data): Product
    {
        return $this->model->create($data);
    }

    public function update(string $id, array $data): bool
    {
        $item = $this->find($id);
        if (! $item) {
            return false;
        }

        return (bool) $item->update($data);
    }

    public function delete(string $id): bool
    {
        $item = $this->find($id);
        if (! $item) {
            return false;
        }

        return (bool) $item->delete();
    }

    public function search(string $term, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%")
            ->paginate($perPage);
    }
}
