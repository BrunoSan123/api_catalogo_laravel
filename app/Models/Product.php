<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    //
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'id',
        'sku',
        'name',
        'description',
        'price',
        'category',
        'status',
        'image_path',
    ];
}
