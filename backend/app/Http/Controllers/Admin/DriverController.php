<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverRequest;
use App\Models\Driver;
use App\Services\DriverService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function __construct(
        private DriverService $driverService
    ) {}

    public function index()
    {
        $drivers = Driver::with('user:id,name,phone,status,avatar_path')
            ->latest()
            ->paginate(15);

        return view(
            'admin.drivers.index',
            compact('drivers')
        );
    }

    public function store(StoreDriverRequest $request)
    {
        $this->driverService->create(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('admin.drivers.index')
            ->with('success', 'Driver account created successfully.');
    }

    public function edit(Driver $driver)
    {
        $driver->load('user');

        if (request()->expectsJson()) {
            return response()->json([
                'id' => $driver->id,
                'name' => $driver->user?->name,
                'email' => $driver->user?->email,
                'phone' => $driver->user?->phone,
                'status' => $driver->user?->status,
                'license_number' => $driver->license_number,
                'vehicle_plate' => $driver->vehicle_plate,
                'can_manage_restricted_fields' => request()->user()->role === 'admin',
            ]);
        }

        return view(
            'admin.drivers.edit',
            compact('driver')
        );
    }

    public function update(
        Request $request,
        Driver $driver
    ) {
        $isAdmin = $request->user()->role === 'admin';
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($driver->user_id),
            ],
            'phone' => [
                'nullable',
                'string',
                'regex:/^09\d{9}$/',
                Rule::unique('users', 'phone')->ignore($driver->user_id),
            ],
            'status' => $isAdmin ? 'required|in:active,inactive' : 'prohibited',
            'license_number' => $isAdmin ? 'nullable|string|max:50' : 'prohibited',
            'vehicle_plate' => $isAdmin ? 'nullable|string|max:20' : 'prohibited',
        ]);

        $this->driverService->update($driver, $data, $request->user());

        if ($request->expectsJson()) {
            $driver->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Driver profile updated.',
                'driver' => [
                    'id' => $driver->id,
                    'name' => $driver->user?->name,
                    'email' => $driver->user?->email,
                    'phone' => $driver->user?->phone,
                    'status' => $driver->user?->status,
                    'license_number' => $driver->license_number,
                    'vehicle_plate' => $driver->vehicle_plate,
                ],
            ]);
        }

        return redirect()
            ->route('admin.drivers.index', $request->query())
            ->with('success', 'Driver updated.');
    }

    public function toggleStatus(Driver $driver)
    {
        $newStatus =
            $driver->user->status === 'active'
            ? 'inactive'
            : 'active';

        $this->driverService->setStatus(
            $driver,
            $newStatus,
            request()->user()
        );

        return redirect()
            ->route('admin.drivers.index')
            ->with('success', 'Driver status updated.');
    }
}
