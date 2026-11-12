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

        return view(
            'admin.drivers.edit',
            compact('driver')
        );
    }

    public function update(
        Request $request,
        Driver $driver
    ) {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($driver->user_id),
            ],
            'phone' => 'nullable|string|max:30',
            'status' => 'required|in:active,inactive',
            'license_number' => 'nullable|string|max:50',
            'vehicle_plate' => 'nullable|string|max:20',
        ]);

        $this->driverService->update($driver, $data, $request->user());

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
