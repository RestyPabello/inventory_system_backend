<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemVariantBarcode extends Model
{
    protected $fillable = [
        'item_variant_id',
        'barcode',
    ];

    public function itemVariant()
    {
        return $this->belongsTo(ItemVariant::class);
    }
}
