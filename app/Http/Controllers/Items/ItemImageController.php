<?php

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadImageRequest;
use App\Models\ItemVariant;
use App\Services\Images\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ItemImageController extends Controller
{
    public function __construct(
        private ImageUploadService $imageUploadService
    ) {}

    /**
     * Upload an image for an item.
     */
    public function store(UploadImageRequest $request): JsonResponse
    {
        $path = $this->imageUploadService->upload($request->file('image'), 'items');
        
        return response()->json([
            'message'       => 'Image uploaded to temporary storage',
            'original_name' => $request->file('image')->getClientOriginalName(),
            'path'          => $path, 
            'url'           => asset('storage/' . $path),
        ]);
    }

    public function destroy(ItemVariant $variant): JsonResponse
    {
        if (!$variant->image) {
            return response()->json(['message' => 'No image to delete'], 400);
        }

        $this->imageUploadService->delete($variant->image);
        $variant->update(['image' => null]);

        return response()->json(['message' => 'Image deleted successfully']);
    }
}
