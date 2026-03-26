<?php

namespace App\Services\Items;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemVariantStock;
use App\Services\Images\ImageUploadService;
use Illuminate\Support\Facades\DB;

class ItemApi
{
    protected $item;
    
    public function __construct(
        Item $item,
        ItemVariant $itemVariant,
        ItemVariantStock $itemVariantStock,
        ImageUploadService $imageUploadService
    )
    {
        $this->item               = $item;
        $this->itemVariant        = $itemVariant;
        $this->itemVariantStock   = $itemVariantStock;
        $this->imageUploadService = $imageUploadService;
    }

    public function getAllItems($request)
    {
        $perPage = min($request->get('per_page', 9), 99);
        $search  = $request->search;

        return DB::table('items as i')
            ->leftJoin('categories as c', 'i.category_id', 'c.id')
            ->leftJoin('item_variants as iv', 'i.id', 'iv.item_id')
            ->leftJoin('units as u', 'iv.unit_id', 'u.id')
            ->leftJoin('item_variant_stocks as ivs', 'iv.id', 'ivs.item_variant_id')
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'i.brand as item_brand',
                'u.name as unit_name',
                'c.name as category_name',
                'iv.image',
                'iv.description',
                'iv.price',
                'ivs.quantity',
                'ivs.status',
                'ivs.expires_at',
                'ivs.purchased_at'
            )
           ->when($search, function ($query, $search) {
                $query->where('i.name', 'like', "%{$search}%");
            })
            ->groupBy('i.name', 'u.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createItem($request)
    {
        return DB::transaction(function () use ($request) {
            $imagePath = null;

            $existingVariant = $this->itemVariant
                ->whereHas('item', function($q) use ($request) {
                    $q->where('name', $request->name);
                })
                ->where('unit_id', $request->unit_id)
                ->where('value', $request->value)
                ->first();


            if ($request->hasFile('image')) {
                $imagePath = $this->imageUploadService
                            ->upload($request->file('image'), 'items');

                if ($existingVariant && $existingVariant->image) {
                    $this->imageUploadService->delete($existingVariant->image);   
                }
            }

            $item = $this->item->firstOrCreate([
                'name' => $request->name 
            ], [
                'brand'       => $request->brand ?? null,
                'description' => $request->item_description,
                'category_id' => $request->category_id
            ]);

            $itemVariant = $item->itemVariants()->updateOrCreate([
                'unit_id' => $request->unit_id,
                'value'   => $request->value,
            ], [
                'image'       => $imagePath ?? $existingVariant->image ?? null,
                'description' => $request->item_variant_description,
                'price'       => $request->price
            ]);

            $itemVariantStock = $itemVariant->itemVariantStocks()->updateOrCreate([
                'item_variant_id' => $itemVariant->id,
                'expires_at'      => $request->expires_at
            ], [
                'quantity'     => $request->quantity,
                'status'       => $request->status,
                'expires_at'   => $request->expires_at,
                'purchased_at' => $request->purchased_at ?? null
            ]);

            return $item->load('itemVariants.itemVariantStocks');
        });
    }

    public function updateItem($request, $id)
    {
        return DB::transaction(function () use ($request, $id) {

            $item = $this->item->findOrFail($id);

            $item->update([
                'name'        => $request->name,
                'brand'       => $request->brand ?? null,
                'description' => $request->item_description,
                'category_id' => $request->category_id,
            ]);

            $itemVariant = $item->itemVariants()
                ->where('unit_id', $request->unit_id)
                ->where('value', $request->value)
                ->update([
                    'image'       => $request->image ?? null,
                    'description' => $request->item_variant_description,
                    'price'       => $request->price
                ]);

            $itemVariant = $item->itemVariants()
                ->where('unit_id', $request->unit_id)
                ->where('value', $request->value)
                ->firstOrFail();

            $itemVariant->itemVariantStocks()
                ->where('expires_at', $request->expires_at)
                ->update([
                    'quantity'     => $request->quantity,
                    'status'       => $request->status,
                    'expires_at'   => $request->expires_at,
                    'purchased_at' => $request->purchased_at ?? null
                ]);

            return $item->load('itemVariants.itemVariantStocks');
        });
    }

    public function stats()
    {
        $totalOutOfStock = $this->itemVariant
            ->whereDoesntHave('itemVariantStocks', function ($query) {
                $query->where('quantity', '>', ItemVariantStock::EMPTY);
            })->count();

        return [
            'total_products'     => $this->item->count(),
            'total_variants'     => $this->itemVariant->count(),
            'total_stock'        => (int) $this->itemVariantStock->sum('quantity'),
            'total_out_of_stock' => $totalOutOfStock,
        ];
    }
}