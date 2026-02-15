<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ProductPostRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Repository\Contracts\ProductRepositoryInterface;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    protected ProductRepositoryInterface $repository;

    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $q = $request->query('q');
        // keep backward compatibility: index supports listing without full search
        if (! $q) {
            $results = $this->repository->paginate($perPage);
            return response()->json($results);
        }

        // If query present, redirect to search logic (cacheable)
        return $this->search($request);
    }

    /**
     * Search products (Elasticsearch-backed). Cached per parameter combination.
     */
    public function search(Request $request): JsonResponse
    {
        $params = $request->only(['q', 'category', 'min_price', 'max_price', 'status', 'sort', 'order', 'page', 'per_page']);
        $params['per_page'] = (int) ($params['per_page'] ?? 15);
        $params['page'] = (int) ($params['page'] ?? 1);

        // Avoid caching for very high pages
        if ($params['page'] > 50) {
            $results = $this->repository->searchElastic($params);
            return response()->json($results);
        }

        // build deterministic cache key
        ksort($params);
        $key = 'search:products:' . md5(json_encode($params));
        $ttl = rand(60, 120);

        $results = Cache::tags(['products_search'])->remember($key, $ttl, function () use ($params) {
            return $this->repository->searchElastic($params);
        });

        return response()->json($results);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductPostRequest $request): JsonResponse
    {
        $data = $request->validated();

        // handle image upload to s3 if provided
        if ($request->hasFile('image_path')) {
            $file = $request->file('image_path');
            $key = 'products/' . (string) Str::uuid() . '.' . $file->getClientOriginalExtension();
            Storage::disk('s3')->put($key, fopen($file->getRealPath(), 'r+'), 'public');
            $data['image_path'] = $key;
        }

        $product = $this->repository->create($data);

        // include temporary signed URL for convenience
        if ($product->image_path) {
            $expires = now()->addMinutes((int) env('S3_URL_EXPIRES', 60));
            $product->image_url = Storage::disk('s3')->temporaryUrl($product->image_path, $expires);
        }

        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $key = "product:{$id}";
        $ttl = rand(60, 120);

        $product = Cache::remember($key, $ttl, function () use ($id) {
            return $this->repository->find($id);
        });

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductUpdateRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();

        $product = $this->repository->find($id);
        if (! $product) {
            return response()->json(['message' => 'Product not found or not updated'], 404);
        }

        // handle image upload: delete old and upload new
        if ($request->hasFile('image_path')) {
            $file = $request->file('image_path');
            $key = 'products/' . (string) Str::uuid() . '.' . $file->getClientOriginalExtension();

            // delete old if exists
            if ($product->image_path) {
                Storage::disk('s3')->delete($product->image_path);
            }

            Storage::disk('s3')->put($key, fopen($file->getRealPath(), 'r+'), 'public');
            $data['image_path'] = $key;
        }

        $updated = $this->repository->update($id, $data);

        if (! $updated) {
            return response()->json(['message' => 'Product not found or not updated'], 404);
        }

        $product = $this->repository->find($id);
        if ($product->image_path) {
            $expires = now()->addMinutes((int) env('S3_URL_EXPIRES', 60));
            $product->image_url = Storage::disk('s3')->temporaryUrl($product->image_path, $expires);
        }

        return response()->json($product);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $product = $this->repository->find($id);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // delete image from S3 if exists (best-effort)
        if ($product->image_path) {
            try {
                Storage::disk('s3')->delete($product->image_path);
            } catch (\Exception $e) {
                report($e);
            }
        }

        $deleted = $this->repository->delete($id);

        if (! $deleted) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json(null, 204);
    }
}
