<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Repository\Contracts\ProductRepositoryInterface;

class ProductControllerUnitTest extends TestCase
{
    public function test_store_calls_repository_and_returns_201(): void
    {
        $payload = [
            'sku' => 'UNIT1',
            'name' => 'Unit Product',
            'price' => 1.23,
            'category' => 'books',
            'status' => 'active',
        ];

        $product = (object) array_merge($payload, ['id' => '11111111-1111-1111-1111-111111111111']);

        $this->mock(ProductRepositoryInterface::class, function ($mock) use ($product) {
            $mock->shouldReceive('create')->once()->andReturn($product);
        });

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(201);
        $response->assertJsonFragment(['sku' => 'UNIT1']);
    }

    public function test_update_returns_404_when_not_found(): void
    {
        $id = '00000000-0000-0000-0000-000000000000';
        $payload = ['sku' => 'X', 'name' => 'X', 'price' => 2.0, 'category' => 'c', 'status' => 'active'];

        $this->mock(ProductRepositoryInterface::class, function ($mock) use ($id) {
            $mock->shouldReceive('find')->with($id)->andReturn(null);
        });

        $response = $this->putJson("/api/products/{$id}", $payload);
        $response->assertStatus(404);
    }

    public function test_show_returns_product(): void
    {
        $id = '22222222-2222-2222-2222-222222222222';
        $product = (object) ['id' => $id, 'sku' => 'S1', 'name' => 'Show', 'price' => 1.0];

        $this->mock(ProductRepositoryInterface::class, function ($mock) use ($id, $product) {
            $mock->shouldReceive('find')->with($id)->andReturn($product);
        });

        $response = $this->getJson("/api/products/{$id}");
        $response->assertStatus(200);
        $response->assertJsonFragment(['sku' => 'S1']);
    }

    public function test_search_uses_repository_and_returns_results(): void
    {
        $hits = ['hits' => ['total' => 1, 'hits' => [['_source' => ['sku' => 'S1']]]]];

        $this->mock(ProductRepositoryInterface::class, function ($mock) use ($hits) {
            $mock->shouldReceive('searchElastic')->once()->andReturn($hits);
        });

        $response = $this->getJson('/api/search/products?q=term&category=books&status=active');
        $response->assertStatus(200);
        $response->assertJsonFragment(['sku' => 'S1']);
    }
}
