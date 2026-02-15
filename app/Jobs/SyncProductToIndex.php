<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ElasticsearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProductToIndex implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public Product|int $product;
    public string $action;

    /**
     * Create a new job instance.
     */
    public function __construct(Product|int $product, string $action = 'index')
    {
        $this->product = $product;
        $this->action = $action;
    }

    /**
     * Execute the job.
     */
    public function handle(ElasticsearchService $es): void
    {
        if ($this->product instanceof Product) {
            $product = $this->product->fresh();
            $id = (string) $product->id;
        } else {
            $id = (string) $this->product;
            $product = null;
        }

        try {
            if ($this->action === 'delete') {
                $es->deleteProduct($id);
                return;
            }

            if (! $product) {
                return;
            }

            if ($this->action === 'index' || $this->action === 'update') {
                $es->indexProduct($product);
            }
        } catch (\Exception $e) {
            // Fail silently or implement retry logic / logging
            report($e);
        }
    }
}
