<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Models\Product;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_product_validation(): void
    {
        $response = $this->postJson('/api/products', [
            'sku' => '',
            'name' => '',
            'price' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sku', 'name', 'price']);
    }

    protected function uploadProductImage(\App\Models\Product $product, ?\Illuminate\Http\UploadedFile $file = null)
    {
        // ensure S3 disk is faked here so S3 is only used by this helper
        \Illuminate\Support\Facades\Storage::fake('s3');

        $file = $file ?? \Illuminate\Http\UploadedFile::fake()->image('pic.jpg');

        return $this->postJson("/api/products/{$product->id}/image", [
            'image' => $file,
        ]);
    }

    public function test_create_product_success(): void
    {
        Storage::fake('s3');

        $file = UploadedFile::fake()->image('photo.jpg');

        $payload = [
            'sku' => 'SKU123',
            'name' => 'Test product',
            'price' => 9.99,
            'category' => 'books',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(201);
        $response->assertJsonFragment(['sku' => 'SKU123', 'name' => 'Test product']);

        $data = $response->json();

        $this->assertDatabaseHas('products', ['sku' => 'SKU123']);
    }

    public function test_update_product(): void
    {
        $product = Product::create([
            'sku' => 'UPD1',
            'name' => 'Old',
            'price' => 5.00,
            'category' => 'cat',
            'status' => 'active',
        ]);

        $payload = [
            'sku' => 'UPD1',
            'name' => 'Updated Name',
            'price' => 10.00,
            'category' => 'cat',
            'status' => 'active',
        ];

        $response = $this->putJson("/api/products/{$product->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Updated Name']);
    }

    public function test_show_product_by_id_and_cache(): void
    {
        $product = Product::create([
            'sku' => 'SH1',
            'name' => 'ShowMe',
            'price' => 3.50,
            'category' => 'c',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/products/{$product->id}");
        $response->assertStatus(200);
        $response->assertJsonFragment(['sku' => 'SH1']);

        // second call should also work (cache used internally)
        $response2 = $this->getJson("/api/products/{$product->id}");
        $response2->assertStatus(200);
    }

    public function test_search_endpoint_with_filters_uses_elasticsearch_mock(): void
    {
        // mock the ElasticsearchService used by ProductRepository
        $this->app->instance(\App\Services\ElasticsearchService::class, new class {
            public function search(array $params): array
            {
                return [
                    'hits' => [
                        'total' => 1,
                        'hits' => [
                            ['_source' => ['sku' => 'S1', 'name' => 'SearchResult', 'category' => $params['category'] ?? null, 'status' => $params['status'] ?? null]],
                        ],
                    ],
                ];
            }
        });

        $response = $this->getJson('/api/search/products?q=Search&category=books&status=active');
        $response->assertStatus(200);
        $response->assertJsonFragment(['sku' => 'S1']);
    }

    public function test_upload_image_for_product_stores_file_and_updates_model()
    {
        Storage::fake('s3');

        $product = Product::create([
            'sku' => 'IMG1',
            'name' => 'Image Product',
            'price' => 1.23,
            'category' => 'cat',
            'status' => 'active',
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('pic.jpg');

        $response = $this->uploadProductImage($product, $file);

        $response->assertStatus(200);
        $response->assertJsonFragment(['sku' => 'IMG1']);

        $data = $response->json();
        $this->assertArrayHasKey('image_path', $data);

        $product->refresh();
        $this->assertNotNull($product->image_path);
        $this->assertEquals($product->image_path, $data['image_path']);
        $this->assertStringStartsWith('products/', $data['image_path']);
        Storage::disk('s3')->assertExists($data['image_path']);
    }
}
