<?php

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemRequest;
use App\Http\Resources\Items\ItemResource;
use App\Models\Item;
use App\Services\Items\ItemApi;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ItemController extends Controller
{
    protected $itemApi;

    public function __construct(ItemApi $itemApi)
    {
        $this->itemApi = $itemApi;
    }

    public function index(Request $request)
    {
        try {
            $result = $this->itemApi->getAllItems($request);

            return response()->json([
                'status_code' => 200,
                'message'     => 'Successful',
                'data'        => $result,
            ]);
        } catch(\Throwable $e) {
            return response()->json([
                'status_code' => 400,
                'message'     => $e->getMessage(),
            ], 400);
        }
    }

    public function store(ItemRequest $request)
    {
        try {
            $item = $this->itemApi->createItem($request);

            return response()->json([
                'status_code' => 201,
                'message'     => 'Successful',
                'data'        => new ItemResource($item)
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'status_code' => 400,
                'message'     => $e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $result = $this->itemApi->updateItem($request, $id);

            return response()->json([
                'status_code' => 200,
                'message'     => 'The item has been successfully updated',
                'data'        => $result
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status_code' => 400,
                'message'     => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy(string $id)
    {
        try {
            $item = Item::findOrFail($id);
            $item->delete();
          
            return response()->json([
                'status_code' => 200,
                'message'     => 'Item has been successfully deleted',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status_code' => 404,
                'message' => 'Item not found with ID ' . $id,
            ], 404);
        }
    }

    public function stats()
    {
        try {
            $stats = $this->itemApi->stats();

            return response()->json([
                'status_code' => 200,
                'message'     => 'Successful',
                'data'        => $stats
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status_code' => 400,
                'message'     => $e->getMessage(),
            ], 400);
        }
    }
}
