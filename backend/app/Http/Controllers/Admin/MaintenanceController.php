<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class MaintenanceController extends Controller
{
    public function index()
    {
        $equipment = Maintenance::orderBy('equipment_name')->get();

        $latestRecords = MaintenanceRecord::with('performedBy:id,name')
            ->whereIn('maintenance_id', $equipment->pluck('id'))
            ->orderBy('performed_at', 'desc')
            ->get()
            ->groupBy('maintenance_id')
            ->map(fn ($records) => $records->first());

        $equipmentData = $equipment->map(function ($item) use ($latestRecords) {
            $lastRecord = $latestRecords->get($item->id);
            return [
                'id' => $item->id,
                'name' => $item->equipment_name,
                'last_performed' => $lastRecord ? Carbon::parse($lastRecord->performed_at) : null,
                'last_record' => $lastRecord,
                'status' => $lastRecord?->equipment_status ?? 'not_recorded',
            ];
        });

        $history = MaintenanceRecord::with([
            'maintenance:id,equipment_name',
            'performedBy:id,name',
        ])
            ->latest('performed_at')
            ->paginate(15);

        $totalCost = Cache::store('file')->remember('admin:maintenance:total_cost', now()->addSeconds(30), function () {
            return (float) MaintenanceRecord::sum('cost');
        });

        $stats = [
            'equipment' => $equipment->count(),
            'underRepair' => $equipmentData->where('status', 'under_repair')->count(),
            'outOfService' => $equipmentData->where('status', 'out_of_service')->count(),
            'totalCost' => $totalCost,
        ];

        return view('admin.maintenance.index', compact('equipmentData', 'history', 'stats'));
    }

    public function storeEquipment(Request $request)
    {
        $data = $request->validate([
            'equipment_name' => 'required|string|max:100|unique:maintenance,equipment_name',
        ]);

        Maintenance::create([
            'equipment_name' => $data['equipment_name'],
            'schedule_interval_days' => 30,
        ]);

        return back()->with('success', 'Equipment added to the maintenance register.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'maintenance_id' => 'required|uuid|exists:maintenance,id',
            'notes' => 'required|string|max:1000',
            'cost' => 'required|numeric|min:0|max:99999999.99',
            'equipment_status' => 'required|in:operational,under_repair,out_of_service',
        ]);

        MaintenanceRecord::create([
            'maintenance_id' => $data['maintenance_id'],
            'performed_by' => $request->user()->id,
            'notes' => $data['notes'],
            'cost' => $data['cost'],
            'equipment_status' => $data['equipment_status'],
            'performed_at' => now(),
        ]);

        Cache::store('file')->forget('admin:maintenance:total_cost');

        return back()->with('success', 'Repair record saved to the equipment history.');
    }
}
