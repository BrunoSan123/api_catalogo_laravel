<?php

namespace App\Services;

use App\Models\Product;
use Elastic\Elasticsearch\ClientBuilder;

class ElasticsearchService
{
    protected $client;

    public function __construct()
    {
        $hosts = config('elasticsearch.hosts') ?? env('ELASTICSEARCH_HOSTS', 'http://elasticsearch:9200');
        $this->client = ClientBuilder::create()->setHosts(is_array($hosts) ? $hosts : [$hosts])->build();
        // comentário teste para commit
    }

    public function indexProduct(Product $product): void
    {
        $this->client->index([
            'index' => 'products',
            'id' => (string) $product->id,
            'body' => [
                'sku' => $product->sku,
                'name' => $product->name,
                'description' => $product->description,
                'price' => (float) $product->price,
                'category' => $product->category,
                'status' => $product->status,
                'created_at' => $product->created_at?->toDateTimeString(),
                'updated_at' => $product->updated_at?->toDateTimeString(),
            ],
        ]);
    }

    public function updateProduct(Product $product): void
    {
        $this->indexProduct($product);
    }

    public function deleteProduct(string $id): void
    {
        $this->client->delete([
            'index' => 'products',
            'id' => $id,
        ]);
    }

    public function search(array $params): array
    {
        $body = ['query' => ['bool' => ['must' => []]]];

        // Full-text query: include name, description and sku
        if (! empty($params['q'])) {
            $body['query']['bool']['must'][] = [
                'multi_match' => [
                    'query' => $params['q'],
                    'fields' => ['name^2', 'description', 'sku'],
                ],
            ];
        }

        // Exact term filters: prefer .keyword fields when mapping uses text
        if (! empty($params['category'])) {
            $body['query']['bool']['must'][] = ['term' => ['category.keyword' => $params['category']]];
        }

        if (! empty($params['status'])) {
            $body['query']['bool']['must'][] = ['term' => ['status.keyword' => $params['status']]];
        }

        // Price range
        if (! empty($params['min_price']) || ! empty($params['max_price'])) {
            $range = [];
            if (! empty($params['min_price'])) {
                $range['gte'] = (float) $params['min_price'];
            }
            if (! empty($params['max_price'])) {
                $range['lte'] = (float) $params['max_price'];
            }
            $body['query']['bool']['must'][] = ['range' => ['price' => $range]];
        }

        // Additional searchable/filterable fields: sku, name, description, price, category, status, created_at
        // Allow filtering by sku exact match
        if (! empty($params['sku'])) {
            $body['query']['bool']['must'][] = ['term' => ['sku.keyword' => $params['sku']]];
        }

        // Allow filtering by name exact (keyword) or as match
        if (! empty($params['name'])) {
            // use match for partial/name text search
            $body['query']['bool']['must'][] = ['match' => ['name' => $params['name']]];
        }

        if (! empty($params['created_at'])) {
            // expect created_at as exact date or range string; try exact match
            $body['query']['bool']['must'][] = ['term' => ['created_at' => $params['created_at']]];
        }

        $sortField = $params['sort'] ?? 'created_at';
        $order = ($params['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $response = $this->client->search([
            'index' => 'products',
            'body' => array_merge($body, ['sort' => [[$sortField => ['order' => $order]]]]),
            'from' => isset($params['page']) && isset($params['per_page']) ? (($params['page'] - 1) * $params['per_page']) : 0,
            'size' => $params['per_page'] ?? 15,
        ]);

        return $response->asArray();
    }
}
