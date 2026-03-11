<?php

namespace App\Http\Resources\Items;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'variants'    => $this->itemVariants->map(function ($variant) {
                return [
                    'id'        => $variant->id,
                    'value'     => $variant->value,
                    'price'     => $variant->price,
                    'image_url' => $variant->image ? asset('storage/' . $variant->image) : null,
                    'stocks'    => $variant->itemVariantStocks,
                ];
            }),
        ];
    }
}
