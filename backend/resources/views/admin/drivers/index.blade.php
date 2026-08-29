<!DOCTYPE html>
<html>
<head>
    <title>Drivers</title>
</head>
<body>

<h1>Drivers</h1>

@if (session('success'))
    <p>{{ session('success') }}</p>
@endif

@if (session('temp_password'))
    <div>
        <h3>Driver Created</h3>

        <p>
            Email:
            <strong>{{ session('driver_email') }}</strong>
        </p>

        <p>
            Temporary Password:
            <strong>{{ session('temp_password') }}</strong>
        </p>

        <p>
            Give this password to the driver.
            It will not be shown again.
        </p>
    </div>
@endif

<a href="{{ route('admin.drivers.create') }}">
    + Add Driver
</a>

<table border="1">
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>License</th>
            <th>Vehicle Plate</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>

    <tbody>

        @foreach ($drivers as $driver)

            <tr>

                <td>
                    {{ $driver->user->name }}
                </td>

                <td>
                    {{ $driver->user->email }}
                </td>

                <td>
                    {{ $driver->license_number }}
                </td>

                <td>
                    {{ $driver->vehicle_plate }}
                </td>

                <td>
                    {{ $driver->user->status }}
                </td>

                <td>

                    <a href="{{ route(
                        'admin.drivers.edit',
                        $driver
                    ) }}">
                        Edit
                    </a>

                    <form
                        method="POST"
                        action="{{ route(
                            'admin.drivers.toggle-status',
                            $driver
                        ) }}"
                        style="display:inline"
                    >

                        @csrf

                        <button type="submit">
                            {{
                                $driver->user->status === 'active'
                                ? 'Deactivate'
                                : 'Activate'
                            }}
                        </button>

                    </form>

                </td>

            </tr>

        @endforeach

    </tbody>
</table>

{{ $drivers->links() }}

</body>
</html>
