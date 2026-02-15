<?php

namespace App\Repository\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function all(): Collection;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function find(int|string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function create(array $data): User;

    public function update(int|string $id, array $data): bool;

    public function delete(int|string $id): bool;
}
