<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageService
{
    public function store(UploadedFile $file): string
    {
        // Use putFile which correctly handles UploadedFile instances and streams
        // Use 'private' visibility to avoid failures on buckets that block public ACLs
        $path = Storage::disk('s3')->putFile('products', $file, 'private');

        if (! $path) {
            throw new \RuntimeException('S3 upload failed for uploaded file');
        }

        return $path;
    }

    public function replace(?string $existingPath, UploadedFile $file): string
    {
        // Store new file first. If upload fails, we keep the existing file.
        $newKey = $this->store($file);

        if ($existingPath) {
            try {
                Storage::disk('s3')->delete($existingPath);
            } catch (\Exception $e) {
                // log but don't fail the overall operation (new file is already stored)
                report($e);
            }
        }

        return $newKey;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        try {
            Storage::disk('s3')->delete($path);
        } catch (\Exception $e) {
            report($e);
        }
    }
}
