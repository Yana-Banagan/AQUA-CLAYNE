<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MaintenanceController extends Controller
{
    public function index()
    {
        $equipment = Maintenance::with(['records' => function ($q) {
            $q->latest('performed_at')->limit(1)->with('performedBy');
        }])->orderBy('equipment_name')->get();

        $equipmentData = $equipment->map(function ($item) {
            $lastRecord = $item->records->first();
            return [
                'id' => $item->id,
                'name' => $item->equipment_name,
                'last_performed' => $lastRecord ? Carbon::parse($lastRecord->performed_at) : null,
                'last_record' => $lastRecord,
                'status' => $lastRecord?->equipment_status ?? 'not_recorded',
            ];
        });

        $history = MaintenanceRecord::with(['maintenance', 'performedBy'])
            ->latest('performed_at')
            ->paginate(15);

        $stats = [
            'equipment' => $equipment->count(),
            'underRepair' => $equipmentData->where('status', 'under_repair')->count(),
            'outOfService' => $equipmentData->where('status', 'out_of_service')->count(),
            'totalCost' => MaintenanceRecord::sum('cost'),
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

        return back()->with('success', 'Repair record saved to the equipment history.');
    }
}
