<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::where('is_active', true)
            ->where('name', 'not like', '%alkaline%')
            ->where('name', 'not like', '%Alkaline%')
            ->with('inventory')
            ->get();

        return $this->success(
            ProductResource::collection($products)
        );
    }

    public function show(Product $product)
    {
        abort_if(!$product->is_active || stripos($product->name, 'alkaline') !== false, 404);

        return $this->success(
            new ProductResource($product->load('inventory'))
        );
    }
}
