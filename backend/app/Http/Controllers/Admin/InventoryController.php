<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallon;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        $consumables = Inventory::with('product')->get();
        $stationGallons = Gallon::with('rfidTag')->paginate(15);

        $stats = [
            'stationGallonsTotal' => Gallon::count(),
            'stationGallonsInStation' => Gallon::where('status', 'in_station')->count(),
            'stationGallonsIssued' => Gallon::where('status', 'issued')->count(),
        ];

        return view('admin.inventory.index', compact('consumables', 'stationGallons', 'stats'));
    }
}
