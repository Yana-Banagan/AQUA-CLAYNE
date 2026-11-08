<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverRequest;
use App\Models\Driver;
use App\Services\DriverService;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(
        private DriverService $driverService
    ) {
    }

    public function index()
    {
        $drivers = Driver::with('user')
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
            'license_number' =>
                'nullable|string|max:50',

            'vehicle_plate' =>
                'nullable|string|max:20',
        ]);

        $driver->update($data);

        return redirect()
            ->route('admin.drivers.index')
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
