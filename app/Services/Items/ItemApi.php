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
                'i.description as item_description',
                'u.id as unit_id',
                'u.name as unit_name',
                'c.id as category_id',
                'c.name as category_name',
                'iv.image',
                'iv.value as item_variant_value',
                'iv.description as item_variant_description',
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
            ->orderBy('ivs.quantity', 'asc')
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
                ->where('value', $request->item_variant_value)
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
                'value'   => $request->item_variant_value,
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

            $itemVariant = $item->itemVariants()->first();

            if (!$itemVariant) {
                throw new \Exception("Item Variant not found for this item.");
            }

            $imagePath = $itemVariant->image;

            if ($request->hasFile('image')) {
                $imagePath = $this->imageUploadService->upload($request->file('image'), 'items');

                if ($itemVariant->image) {
                    $this->imageUploadService->delete($itemVariant->image);   
                }
            }

            $itemVariant->update([
                'image'       => $imagePath, 
                'description' => $request->item_variant_description,
                'price'       => $request->price,
                'unit_id'     => $request->unit_id
            ]);

            $itemVariant->itemVariantStocks()->update([
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

        $totalProducts = DB::table(function ($query) {
            $query->from('items')
                ->join('item_variants as iv', 'items.id', 'iv.item_id')
                ->join('units as u', 'iv.unit_id', 'u.id')
                ->groupBy('items.id', 'u.id')
                ->select('items.id as item_id', 'u.id as unit_id');
        }, 'sub')->count();

        return [
            'total_products'     => $totalProducts,
            'total_variants'     => $this->itemVariant->count(),
            'total_stock'        => (int) $this->itemVariantStock->sum('quantity'),
            'total_out_of_stock' => $totalOutOfStock,
        ];
    }
}