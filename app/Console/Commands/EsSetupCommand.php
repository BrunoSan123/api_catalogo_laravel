<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EsSetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure Elasticsearch index mappings are applied';

    public function handle()
    {
        $host = env('ELASTICSEARCH_HOST', 'http://elasticsearch:9200');
        $this->info("Waiting for Elasticsearch at {$host}...");

        $attempts = 0;
        while ($attempts < 60) {
            try {
                $res = Http::get($host);
                if ($res->successful()) {
                    $this->info('Elasticsearch is available');
                    break;
                }
            } catch (\Exception $e) {
                // ignore and retry
            }
            $attempts++;
            sleep(1);
        }

        $mappingPath = base_path('mapping_quick.json');
        if (! file_exists($mappingPath)) {
            $this->error("Mapping file not found: {$mappingPath}");
            return 1;
        }

        $this->info('Applying mapping to index `products` (quick fix)');
        $mapping = file_get_contents($mappingPath);

        try {
            $resp = Http::withHeaders(['Content-Type' => 'application/json'])
                ->put(rtrim($host, '/').'/products/_mapping', json_decode($mapping, true));

            if ($resp->successful()) {
                $this->info('Mapping applied successfully');
            } else {
                $this->error('Failed to apply mapping: '.$resp->body());
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Error while applying mapping: '.$e->getMessage());
            return 1;
        }

        return 0;
    }
}
