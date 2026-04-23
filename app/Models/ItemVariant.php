<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemVariant extends Model
{
    protected $table = 'item_variants';

    protected $fillable = [
        'item_id',
        'unit_id', 
        'value',
        'image', 
        'description',
        'price'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function itemVariantStocks()
    {
        return $this->hasMany(ItemVariantStock::class);
    }

    public function itemVariantBarcodes()
    {
        return $this->hasMany(ItemVariantBarcode::class);
    }
}
