<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends Model
{
    use HasFactory;

    protected $table = 'items';

    protected $fillable = [
        'name', 
        'brand', 
        'description', 
        'category_id',
        'unit_id'
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer'
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function itemVariants()
    {
        return $this->hasMany(ItemVariant::class);
    }
}
