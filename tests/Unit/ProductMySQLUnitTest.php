<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

/**
 * @group integration
 */
class ProductMySQLUnitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Force tests in this class to use the mysql connection. When running
        // make sure the env variables (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
        // point to a running MySQL instance (for example via Docker Compose).
        Config::set('database.default', 'mysql');
        // Ensure a sensible mysql database name is used (fallback to test_db)
        Config::set('database.connections.mysql.database', env('DB_DATABASE', 'test_db'));
    }

    public function test_create_product_using_mysql_connection(): void
    {
        Storage::fake('s3');

        $payload = [
            'sku' => 'UNIT-MYSQL-1',
            'name' => 'Unit MySQL Product',
            'price' => 4.56,
            'category' => 'books',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['sku' => 'UNIT-MYSQL-1']);

        // Now assert the product is searchable via the Elasticsearch-backed
        // search endpoint. The queue is configured as `sync` in tests, so the
        // indexing job runs immediately. We'll poll the search endpoint a few
        // times (with short sleeps) to allow ES to index the document.
        $found = false;
        $attempts = 10;

        for ($i = 0; $i < $attempts; $i++) {
            $search = $this->getJson('/api/search/products?q=UNIT-MYSQL-1&category=books&status=active');

            if ($search->status() === 200) {
                $data = $search->json();
                if (isset($data['hits']['hits']) && is_array($data['hits']['hits'])) {
                    foreach ($data['hits']['hits'] as $hit) {
                        $source = $hit['_source'] ?? [];
                        if (isset($source['sku']) && $source['sku'] === 'UNIT-MYSQL-1') {
                            $found = true;
                            break 2;
                        }
                    }
                }
            }

            // small backoff
            sleep(1);
        }

        $this->assertTrue($found, 'Expected product to appear in Elasticsearch search results');
    }

    public function test_update_product_using_mysql_connection(): void
    {
        Storage::fake('s3');

        $payload = [
            'sku' => 'UNIT-MYSQL-UPD',
            'name' => 'Before Update',
            'price' => 5.00,
            'category' => 'books',
            'status' => 'active',
        ];

        $create = $this->postJson('/api/products', $payload);
        $create->assertStatus(201);
        $id = $create->json('id');

        $updatePayload = [
            'sku' => 'UNIT-MYSQL-UPD',
            'name' => 'After Update',
            'price' => 6.50,
            'category' => 'books',
            'status' => 'active',
        ];

        $resp = $this->putJson("/api/products/{$id}", $updatePayload);
        $resp->assertStatus(200);
        $resp->assertJsonFragment(['name' => 'After Update']);

        $this->assertDatabaseHas('products', ['id' => $id, 'name' => 'After Update']);

        // ensure search reflects updated data
        $found = false;
        for ($i = 0; $i < 8; $i++) {
            $search = $this->getJson('/api/search/products?q=After Update&category=books&status=active');
            if ($search->status() === 200) {
                $data = $search->json();
                if (isset($data['hits']['hits']) && is_array($data['hits']['hits'])) {
                    foreach ($data['hits']['hits'] as $hit) {
                        $source = $hit['_source'] ?? [];
                        if (isset($source['sku']) && $source['sku'] === 'UNIT-MYSQL-UPD') {
                            $found = true;
                            break 2;
                        }
                    }
                }
            }
            sleep(1);
        }

        $this->assertTrue($found, 'Expected updated product to appear in Elasticsearch search results');
    }

    public function test_delete_product_using_mysql_connection(): void
    {
        Storage::fake('s3');

        $payload = [
            'sku' => 'UNIT-MYSQL-DEL',
            'name' => 'To Be Deleted',
            'price' => 7.00,
            'category' => 'books',
            'status' => 'active',
        ];

        $create = $this->postJson('/api/products', $payload);
        $create->assertStatus(201);
        $id = $create->json('id');

        $del = $this->deleteJson("/api/products/{$id}");
        $del->assertStatus(204);

        // Soft deleted in DB
        $this->assertSoftDeleted('products', ['id' => $id]);

        // Ensure it no longer appears in ES search results
        $found = false;
        for ($i = 0; $i < 8; $i++) {
            $search = $this->getJson('/api/search/products?q=UNIT-MYSQL-DEL&category=books&status=active');
            if ($search->status() === 200) {
                $data = $search->json();
                if (isset($data['hits']['hits']) && is_array($data['hits']['hits'])) {
                    foreach ($data['hits']['hits'] as $hit) {
                        $source = $hit['_source'] ?? [];
                        if (isset($source['sku']) && $source['sku'] === 'UNIT-MYSQL-DEL') {
                            $found = true;
                            break 2;
                        }
                    }
                }
            }
            sleep(1);
        }

        $this->assertFalse($found, 'Expected deleted product to be removed from Elasticsearch search results');
    }
}
