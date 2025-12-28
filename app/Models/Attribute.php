<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_id',
        'key',
        'value',
    ];

    public function product(){
        return $this->belongsTo(Product::class);
    }
}
