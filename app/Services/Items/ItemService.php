<?php
namespace App\Services\Items;

use Illuminate\Http\UploadedFile;

class ItemService
{
    public function __construct(
        protected ItemApi $itemApi
    ) {}

    public function scanImage(UploadedFile $image): array
    {
        $decoded = $this->itemApi->scanImage($image);

        if (empty($decoded['is_product']) || $decoded['is_product'] === false) {
            throw new \Exception("Invalid image. Please upload a product image.");
        }

        $categoryMap = [
            'food'          => 1,
            'drinks'        => 2,
            'snacks'        => 3,
            'personal care' => 4,
            'household'     => 5,
            'condiments'    => 6,
            'dairy'         => 7,
        ];

        $decoded['category_id'] = $categoryMap[strtolower($decoded['category'] ?? '')] ?? null;
        unset($decoded['category']);
        unset($decoded['is_product']);

        return $decoded;
    }
}