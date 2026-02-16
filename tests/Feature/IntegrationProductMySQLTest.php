<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

/**
 * @group integration
 */
class IntegrationProductMySQLTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Force the test to use MySQL connection. Ensure your MySQL service
        // is running and the test database exists (see README). The
        // connection parameters are taken from the environment (DB_*) when
        // running inside the docker compose stack.
        Config::set('database.default', 'mysql');
        // Ensure a sensible mysql database name is used (fallback to test_db)
        Config::set('database.connections.mysql.database', env('DB_DATABASE', 'test_db'));

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
}
