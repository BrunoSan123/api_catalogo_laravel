<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Models\Product;

/**
 * @group integration
 */
class IntegrationProductMySQLTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Ensure environment variables instruct Laravel to use MySQL
        // before the application/test framework boots. Using putenv here
        // avoids calling Facade helpers before the container is ready.
        putenv('DB_CONNECTION=mysql');
        $db = getenv('DB_DATABASE') ?: 'test_db';
        putenv('DB_DATABASE=' . $db);

        parent::setUp();

        // If needed, override database name to 'test_db' (optional):
        // Config::set('database.connections.mysql.database', env('DB_DATABASE', 'test_db'));
    }

    public function test_create_and_fetch_product_using_mysql(): void
    {
        // ensure no external storage interactions
        Storage::fake('s3');

        $payload = [
            'sku' => 'MYSQL-INT-1',
            'name' => 'Integration Product',
            'price' => 12.34,
            'category' => 'books',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['sku' => 'MYSQL-INT-1']);

        $id = $response->json('id');

        $show = $this->getJson("/api/products/{$id}");
        $show->assertStatus(200);
        $show->assertJsonFragment(['sku' => 'MYSQL-INT-1']);
    }

    public function test_upload_image_endpoint_with_s3_disk_integration(): void
    {
        // Call the real controller upload endpoint and verify response.
        // IMPORTANT: do not fake or remap the `s3` disk — the controller
        // will perform the upload using the configured `s3` disk.

        $product = Product::create([
            'sku' => 'INT-S3-1',
            'name' => 'Integration S3 Product',
            'price' => 7.77,
            'category' => 'misc',
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->image('int.jpg');

        // call the real endpoint (no Storage::fake here)
        $response = $this->postJson("/api/products/{$product->id}/image", [
            'image' => $file,
        ]);

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('image_path', $data);
        $this->assertStringStartsWith('products/', $data['image_path']);

        // verify model persisted the path (controller should have updated it)
        $product->refresh();
        $this->assertEquals($product->image_path, $data['image_path']);
    }
}
