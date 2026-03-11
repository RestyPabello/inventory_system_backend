<?php

namespace App\Services\Images;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    /**
     * @param UploadedFile $file
     * @param string $folder
     * @return string
     */
    public function upload(UploadedFile $file, string $folder = 'products'): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        Storage::disk('public')->putFileAs($folder, $file, $filename);

        return $folder . '/' . $filename;
    }

    /**
     * @param string $path
     * @return bool
     */
    public function delete(?string $path): bool
    {
        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return false;
    }
}