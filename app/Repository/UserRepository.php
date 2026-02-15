<?php

namespace App\Repository;

use App\Models\User;
use App\Repository\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements UserRepositoryInterface
{
    protected User $model;

    public function __construct(?User $model = null)
    {
        $this->model = $model ?? new User();
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->paginate($perPage);
    }

    public function find(int|string $id): ?User
    {
        return $this->model->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    public function update(int|string $id, array $data): bool
    {
        $item = $this->find($id);
        if (! $item) {
            return false;
        }

        return $item->update($data);
    }

    public function delete(int|string $id): bool
    {
        $item = $this->find($id);
        if (! $item) {
            return false;
        }

        return (bool) $item->delete();
    }
}
