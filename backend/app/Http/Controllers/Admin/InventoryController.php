<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallon;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InventoryController extends Controller
{
    public function index()
    {
        $consumables = Inventory::with('product:id,name,price,category')->get();
        $stationGallons = Gallon::with('rfidTag:id,gallon_id,tag_uid')->paginate(15);

        $stats = Cache::store('file')->remember('admin:inventory:stats', now()->addSeconds(30), function () {
            $row = Gallon::selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'in_station' THEN 1 ELSE 0 END) AS in_station,
                SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) AS issued
            ")->first();

            return [
                'stationGallonsTotal' => (int) ($row?->total ?? 0),
                'stationGallonsInStation' => (int) ($row?->in_station ?? 0),
                'stationGallonsIssued' => (int) ($row?->issued ?? 0),
            ];
        });

        return view('admin.inventory.index', compact('consumables', 'stationGallons', 'stats'));
    }
}
