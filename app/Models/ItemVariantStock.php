<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemVariantStock extends Model
{
    public const EMPTY = 0;

    protected $fillable = [
        'item_variant_id',
        'quantity', 
        'status',
        'expires_at', 
        'purchased_at'
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer'
        ];
    }

    public function itemVariant()
    {
        return $this->belongsTo(ItemVariant::class);
    }
}
