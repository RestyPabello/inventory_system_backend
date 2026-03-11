<?php

namespace Tests\Unit;

use App\Services\Images\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadServiceTest extends TestCase
{
    public function testUploadStoresFileAndReturnsPath()
    {
        Storage::fake('public');

        $folder  = 'test-uploads';
        $file    = UploadedFile::fake()->image('test.jpg');

        $service = new ImageUploadService();
        $path    = $service->upload($file, $folder);

        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith($folder . '/', $path);
        $this->assertStringEndsWith('.jpg', $path);
    }

    public function testDeleteRemovesFile()
    {
        Storage::fake('public');

        $service = new ImageUploadService();
        $file    = UploadedFile::fake()->image('test.jpg');
        $path    = $service->upload($file, 'temp');

        $result  = $service->delete($path);

        $this->assertTrue($result);
        Storage::disk('public')->assertMissing($path);
    }
}