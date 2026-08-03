<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'source_id',
        'shop_url',
        'sku',
        'title',
        'description',
        'link',
        'main_image',
        'additional_images',
        'price',
        'sale_price',
        'reference',
        'colour',
        'size',
        'stock',
        'nl_stock',
        'uk_stock',
        'categories',
        'raw',
        'variant_id'
    ];

    protected $casts = [
        'additional_images' => 'array',
        'categories' => 'array',
        'raw' => 'array',
        'attributes' => 'array',
    ];
    
    public function attributes(){
        return $this->hasMany(Attribute::class);
    }
}
