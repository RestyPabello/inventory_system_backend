<?php

namespace App\Services\Items;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemVariantStock;
use App\Services\Images\ImageUploadService;
use Illuminate\Http\UploadedFile; 
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
        $page    = $request->get('page', 1); 

        $cacheKey = "items_search_" . ($search ?? 'all') . "_page_{$page}_perpage_{$perPage}";

        return Cache::remember($cacheKey, 300, function () use ($search, $perPage) {
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
         });
    }

    public function createItem($request)
    {
        $result = DB::transaction(function () use ($request) {
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

        Cache::flush();
        return $result;
    }

    public function updateItem($request, $id)
    {
        $result = DB::transaction(function () use ($request, $id) {
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

        Cache::flush();
        return $result;
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

    public function scanImage(UploadedFile $image): array
    {
        $base64Image = base64_encode(file_get_contents($image));
        $mimeType    = $image->getMimeType();

        $model = config('services.gemini.model');
        $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post($url . '?key=' . config('services.gemini.key'), [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => "Return a JSON object only. No markdown. Analyze this image and output: " .
                                          "is_product (boolean - true if this is a product/item image, false if not), " .
                                          "name (string), brand (string), price (number), " .
                                          "category (one of: Food, Drinks, Snacks, Personal Care, Household, Condiments, Dairy). " .
                                          "If unknown, set as null.",
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data'      => $base64Image,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception("AI Service Error: " . $response->body());
        }

        $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$text) {
            throw new \Exception("AI failed to return a valid response.");
        }

        return $this->parseAiResponse($text);
    }

    // Cleans and parses the raw AI text response into a decoded JSON array
    private function parseAiResponse(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/^```\s*/i', '', $text);
        $text = preg_replace('/```\s*$/i', '', $text);
        $text = trim($text);

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Failed to parse AI response as JSON: " . $text);
        }

        return $decoded;
    }
}