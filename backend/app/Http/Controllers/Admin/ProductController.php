<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('inventory:id,product_id,quantity_on_hand,reorder_level')->latest()->paginate(15);

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        return view('admin.products.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'image_url' => 'nullable|string|max:2048',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->has('is_active');

        DB::transaction(function () use ($data) {
            $product = Product::create($data);

            // Every refill service begins the day with the standard station stock.
            Inventory::create([
                'product_id' => $product->id,
                'quantity_on_hand' => 100,
                'reorder_level' => 10,
            ]);
        });

        return redirect()->route('admin.products.index')->with('success', 'Refill service offering created with 100 stock.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'image_url' => 'nullable|string|max:2048',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->has('is_active');

        $product->update($data);

        return redirect()
            ->route('admin.products.index', $request->query())
            ->with('success', 'Refill service offering updated.');
    }
}
